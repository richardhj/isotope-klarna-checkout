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
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Config;
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Isotope\Model\Shipping;
use Klarna\Rest\Checkout\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Klarna\Rest\Transport\Exception\ConnectorException;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\UpdateAddressTrait;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;

class KlarnaCheckoutConfirmation extends Module
{

    use UpdateAddressTrait;

    /**
     * Template
     *
     * @var string
     */
    protected $strTemplate = 'mod_klarna_checkout_confirmation';

    /**
     * @var Config|Model
     */
    private $config;

    /**
     * KlarnaCheckoutConfirmation constructor.
     *
     * @param ModuleModel $module
     * @param string      $column
     */
    public function __construct($module, $column = 'main')
    {
        parent::__construct($module, $column);

        $this->config = Isotope::getConfig();
    }

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
     * @throws RequestException          When an error is encountered
     * @throws PageNotFoundException     If order is not found in Klarna system.
     * @throws RedirectResponseException If the checkout is not completed yet.
     * @throws \RuntimeException         On an unexpected API response
     * @throws \RuntimeException         If the response content type is not JSON
     * @throws ConnectorException        When the API replies with an error response
     * @throws \InvalidArgumentException If the JSON cannot be parsed
     * @throws \LogicException           If Klarna not configured in Isotope config.
     * @throws ServiceNotFoundException
     * @throws ServiceCircularReferenceException
     */
    protected function compile()
    {
        if (!$this->config->use_klarna) {
            $this->Template->gui = sprintf('Klarna not configured for "%s"', $config->name);

            return;
        }

        /** @var Request $request */
        $request     = System::getContainer()->get('request_stack')->getCurrentRequest();
        $orderId     = $request->query->get('klarna_order_id');
        $apiUsername = $this->config->klarna_api_username;
        $apiPassword = $this->config->klarna_api_password;
        $connector   = KlarnaConnector::create(
            $apiUsername,
            $apiPassword,
            $this->config->klarna_api_test ? ConnectorInterface::EU_TEST_BASE_URL : ConnectorInterface::EU_BASE_URL
        );

        $klarnaCheckout = new KlarnaOrder($connector, $orderId);
        try {
            $klarnaCheckout->fetch();
        } catch (ClientException $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                throw new PageNotFoundException('Order not found: ID '.$orderId);
            }

            $this->Template->gui = $e->getResponse()->getReasonPhrase();

            return;
        }

        if ('checkout_incomplete' === $klarnaCheckout['status']) {
            // Checkout incomplete. Back to the checkout.
            $page = PageModel::findById($this->klarna_checkout_page);
            $uri  = (null !== $page) ? $page->getFrontendUrl() : '';

            throw new RedirectResponseException($uri);
        }

        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $klarnaCheckout->getId());

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
        $isotopeOrder->setShippingMethod(Shipping::findByIdOrAlias($selectedShipping['id']));

        // Save and complete order
        $isotopeOrder->save();
        $isotopeOrder->checkout();
        $isotopeOrder->complete();

        $this->Template->gui = $klarnaCheckout['html_snippet'];
    }
}
