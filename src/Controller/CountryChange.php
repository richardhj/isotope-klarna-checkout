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


use Isotope\Isotope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CountryChange
{

    /**
     * @param Request $request The request.
     *
     * @return void
     *
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __invoke(Request $request)
    {
        $data = json_decode($request->getContent());

        $billingAddress = $data->billing_address;
        $billingCountry = $billingAddress->country;

        $config = Isotope::getConfig();
        $allowedCountries = $config->getBillingCountries();

        if (!\in_array($billingCountry, $allowedCountries, true)) {
            $response = new JsonResponse(['error_type' => 'unsupported_shipping_address']);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit;
        }

        $response = new JsonResponse([]);
        $response->send();
    }
}
