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
use Contao\Model;
use Isotope\Model\ProductCollection\Cart;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CountryChange
{

    /**
     * Will be called whenever the consumer changes billing address country. Time to update shipping, tax and purchase
     * currency.
     * The response will contain an error if the billing country is not supported as per shop config.
     *
     * @param integer $orderId The checkout order id.
     * @param Request $request The request.
     *
     * @return void
     *
     * @throws PageNotFoundException If page is requested without data.
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __invoke($orderId, Request $request)
    {
        $data = json_decode($request->getContent());
        if (null === $data) {
            throw new PageNotFoundException('Page call not valid.');
        }

        $billingAddress = $data->billing_address;
        $billingCountry = $billingAddress->country;

        /** @var Cart|Model $cart */
        $cart   = Cart::findOneBy('klarna_order_id', $orderId);
        $config = $cart->getConfig();
        if (null === $config) {
            $this->errorResponse();
        }

        $allowedCountries = $config->getBillingCountries();
        if (!\in_array($billingCountry, $allowedCountries, true)) {
            $this->errorResponse();
        }

        $response = new JsonResponse($data);
        $response->send();
    }

    /**
     * Send a response that will display an error to the customer.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function errorResponse()
    {
        $response = new JsonResponse(['error_type' => 'unsupported_shipping_address']);
        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $response->send();
        exit;
    }
}
