<?php

/**
 * This file is part of richardhj/isotope-klarna-checkout.
 *
 * Copyright (c) 2018-2018 Richard Henkenjohann
 *
 * @package   richardhj/isotope-klarna-checkout
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2018-2018 Richard Henkenjohann
 * @license   https://github.com/richardhj/isotope-klarna-checkout/blob/master/LICENSE LGPL-3.0
 */

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Module;


use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Model;
use Contao\Module;
use Contao\PageModel;
use Contao\System;
use GuzzleHttp\Exception\ClientException;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Config;
use Isotope\Model\Shipping;
use Klarna\Rest\Checkout\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;

class KlarnaCheckoutConfirmation extends Module
{

    /**
     * Template
     *
     * @var string
     */
    protected $strTemplate = 'mod_klarna_checkout_confirmation';

    /**
     * Parse the template
     *
     * @return string
     */
    public function generate(): string
    {
        if ('BE' === TL_MODE) {
            $template = new BackendTemplate('be_wildcard');
            $template->setData(
                [
                    'wildcard' => '### '.strtoupper($GLOBALS['TL_LANG']['FMD'][$this->type][0]).' ###',
                    'title'    => $this->headline,
                    'id'       => $this->id,
                    'link'     => $this->name,
                    'href'     => 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id,
                ]
            );

            return $template->parse();
        }

        return parent::generate();
    }

    /**
     * Compile the current element
     *
     * @return void
     *
     * @throws \GuzzleHttp\Exception\RequestException
     * @throws \Contao\CoreBundle\Exception\PageNotFoundException
     * @throws RedirectResponseException If the checkout is not completed yet.
     * @throws \RuntimeException
     * @throws \Klarna\Rest\Transport\Exception\ConnectorException
     * @throws \InvalidArgumentException
     * @throws \LogicException If Klarna not configured in Isotope config.
     * @throws ServiceNotFoundException
     * @throws ServiceCircularReferenceException
     */
    protected function compile()
    {
        /** @var Config|Model $config */
        $config = Isotope::getConfig();
        if (!$config->use_klarna) {
            throw new \LogicException('Klarna is not configured in the Isotope config.');
        }

        /** @var Request $request */
        $request     = System::getContainer()->get('request_stack')->getCurrentRequest();
        $orderId     = $request->query->get('klarna_order_id');
        $apiUsername = $config->klarna_api_username;
        $apiPassword = $config->klarna_api_password;
        $connector   = KlarnaConnector::create($apiUsername, $apiPassword, ConnectorInterface::EU_TEST_BASE_URL);

        $klarnaCheckout = new KlarnaOrder($connector, $orderId);
        try {
            $klarnaCheckout->fetch();
        } catch (ClientException $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                throw new PageNotFoundException('Order not found: ID '.$orderId);
            }
            return;
        }

        if ('checkout_incomplete' === $klarnaCheckout['status']) {
            // Checkout incomplete. Back to the checkout.
            $page = PageModel::findById($this->klarna_checkout_page);
            $uri  = (null !== $page) ? $page->getFrontendUrl() : '';

            throw new RedirectResponseException($uri);
        }

        // Create order
        $isotopeOrder = Isotope::getCart()->getDraftOrder();

        $isotopeOrder->klarna_order_id      = $orderId;
        $isotopeOrder->nc_notification      = $this->nc_notification;
        $isotopeOrder->iso_addToAddressbook = $this->iso_addToAddressbook;

        $billingAddress  = $klarnaCheckout['billing_address'];
        $shippingAddress = $klarnaCheckout['shipping_address'];

        // Update billing address
        $address = $isotopeOrder->getBillingAddress();
        $address = $address ?? Address::createForProductCollection($isotopeOrder);
        $address = $this->updateAddressByApiResponse($address, $billingAddress);

        $isotopeOrder->setBillingAddress($address);

        // Update shipping address
        if ($shippingAddress !== $billingAddress) {
            $address = $isotopeOrder->getShippingAddress();
            $address = $address ?? Address::createForProductCollection($isotopeOrder);
            $address = $this->updateAddressByApiResponse($address, $shippingAddress);
        }
        $isotopeOrder->setShippingAddress($address);

        // Update shipping method
        $selectedShipping = $klarnaCheckout['selected_shipping_option'];
        $isotopeOrder->setShippingMethod(Shipping::findByIdOrAlias($selectedShipping->id));

        // Save and lock order
        $isotopeOrder->save();
        $isotopeOrder->lock();

        $this->Template->gui = $klarnaCheckout['html_snippet'];
    }

    /**
     * @param Address|Model $address
     * @param array         $data
     *
     * @return Address
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function updateAddressByApiResponse(Address $address, array $data): Address
    {
        $address->company    = $data['organization_name'];
        $address->firstname  = $data['given_name'];
        $address->lastname   = $data['family_name'];
        $address->email      = $data['email'];
        $address->salutation = $data['title'];
        $address->street_1   = $data['street_address'];
        $address->street_2   = $data['street_address2'];
        $address->postal     = $data['postal_code'];
        $address->city       = $data['city'];
        $address->country    = $data['country'];

        $address->save();

        return $address;
    }
}
