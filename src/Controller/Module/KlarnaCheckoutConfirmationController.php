<?php

declare(strict_types=1);

/*
 * This file is part of richardhj/isotope-klarna-checkout.
 *
 * Copyright (c) 2018-2021 Richard Henkenjohann
 *
 * @package   richardhj/isotope-klarna-checkout
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2018-2021 Richard Henkenjohann
 * @license   https://github.com/richardhj/isotope-klarna-checkout/blob/master/LICENSE LGPL-3.0
 */

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller\Module;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Isotope\Model\Shipping;
use Richardhj\IsotopeKlarnaCheckoutBundle\Api\ApiClient;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule("iso_klarna_checkout_confirmation", category="isotope", template="mod_klarna_checkout_confirmation")
 */
class KlarnaCheckoutConfirmationController extends AbstractFrontendModuleController
{
    private ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $isoConfig = Isotope::getConfig();

        if (!$isoConfig->use_klarna) {
            $template->gui = sprintf('Klarna not configured for "%s"', $isoConfig->name);

            return $template->getResponse();
        }

        // Nothing to do here for external payment methods.
        if (null !== $request->query->get('uid')) {
            return $template->getResponse();
        }

        $client = $this->apiClient->httpClient($isoConfig);

        $orderId = $request->query->get('klarna_order_id');
        $response = $client->request('GET', '/checkout/v3/orders/'.$orderId);

        try {
            $klarnaOrder = $response->toArray();
        } catch (ClientException $e) {
            $response = $e->getResponse();
            if (404 === $response->getStatusCode()) {
                throw new PageNotFoundException('Klarna order not found: ID '.$orderId);
            }

            $error = $response->toArray(false);
            $template->gui = $error['error_message'] ?? implode(' ', $error['error_messages'] ?? []);

            return $template->getResponse();
        }

        // Checkout incomplete. Back to the checkout.
        if ('checkout_incomplete' === $klarnaOrder['status']) {
            $page = PageModel::findById($model->klarna_checkout_page);
            $uri = (null !== $page) ? $page->getFrontendUrl() : '';

            return new RedirectResponse($uri);
        }

        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $klarnaOrder['order_id']);
        if (null === $isotopeOrder) {
            throw new PageNotFoundException('Isotope order not found: Klarna ID'.$klarnaOrder['order_id']);
        }

        $template->gui = $klarnaOrder['html_snippet'];
        if ($isotopeOrder->isCheckoutComplete()) {
            return $template->getResponse();
        }

        $isotopeOrder->nc_notification = $model->nc_notification;
        $isotopeOrder->iso_addToAddressbook = $model->iso_addToAddressbook;

        $billingAddress = $klarnaOrder['billing_address'];
        $shippingAddress = $klarnaOrder['shipping_address'];

        // Update billing address
        $address = $isotopeOrder->getBillingAddress() ?? Address::createForProductCollection($isotopeOrder);
        $address = $this->apiClient->updateAddressByApiResponse($address, $billingAddress);

        $isotopeOrder->setBillingAddress($address);

        // Update shipping address
        if ($shippingAddress !== $billingAddress) {
            $address = $isotopeOrder->getShippingAddress() ?? Address::createForProductCollection($isotopeOrder);
            $address = $this->apiClient->updateAddressByApiResponse($address, $shippingAddress);
        }
        $isotopeOrder->setShippingAddress($address);

        // Update shipping method
        $selectedShipping = $klarnaOrder['selected_shipping_option'];
        $isotopeOrder->setShippingMethod(Shipping::findById($selectedShipping['id']));

        // Save and complete order
        $isotopeOrder->save();
        $isotopeOrder->checkout();
        $isotopeOrder->complete();

        return $template->getResponse();
    }
}
