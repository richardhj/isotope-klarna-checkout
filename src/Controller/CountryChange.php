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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\ModuleModel;
use Contao\StringUtil;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Cart;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetShippingOptionsTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\UpdateAddressTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CountryChange
{

    use GetOrderLinesTrait;
    use GetShippingOptionsTrait;
    use UpdateAddressTrait;

    /**
     * Will be called whenever the consumer changes billing address country. Time to update shipping, tax and purchase
     * currency.
     * The response will contain an error if the billing country is not supported as per shop config.
     */
    public function __invoke($orderId, Request $request): Response
    {
        $data = json_decode($request->getContent());
        if (null === $data) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        $billingAddress = $data->billing_address;
        $billingCountry = $billingAddress->country;

        $this->cart = Cart::findOneBy('klarna_order_id', $orderId);
        $config     = $this->cart->getConfig();
        if (null === $config) {
            return new JsonResponse(['error_type' => 'unsupported_shipping_address'], Response::HTTP_BAD_REQUEST);
        }

        $allowedCountries = $config->getBillingCountries();
        if (!\in_array($billingCountry, $allowedCountries, true)) {
            return new JsonResponse(['error_type' => 'unsupported_shipping_address'], Response::HTTP_BAD_REQUEST);
        }

        $billingAddress  = $data->billing_address;
        $shippingAddress = $data->shipping_address;
        $checkoutModule  = ModuleModel::findById($this->cart->klarna_checkout_module);
        if (null === $checkoutModule) {
            return new JsonResponse(['error_type' => 'unsupported_shipping_address'], Response::HTTP_BAD_REQUEST);
        }

        // Set billing address
        $address = $this->cart->getBillingAddress();
        $address = $this->updateAddressByApiResponse($address, (array) $billingAddress);

        $this->cart->setBillingAddress($address);

        // Set shipping address
        $address = $this->cart->getShippingAddress();
        $address = $this->updateAddressByApiResponse($address, (array) $shippingAddress);

        $this->cart->setShippingAddress($address);

        $this->cart->save();

        // Set cart to prevent errors within the Isotope logic.
        Isotope::setCart($this->cart);

        // Since we updated the shipping address, now we can fetch the current shipping methods.
        $shippingOptions = $this->shippingOptions(StringUtil::deserialize($checkoutModule->iso_shipping_modules, true));
        if ([] === $shippingOptions) {
            return new JsonResponse(['error_type' => 'unsupported_shipping_address'], Response::HTTP_BAD_REQUEST);
        }

        // Update order since shipping method may get updated
        $data->shipping_options = $shippingOptions;
        $data->order_amount     = (int) round($this->cart->getTotal() * 100, 0);
        $data->order_tax_amount = (int) round(($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100, 0);
        $data->order_lines      = $this->orderLines();

        return new JsonResponse($data);
    }
}
