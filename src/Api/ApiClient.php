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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Api;

use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Config;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;
use Richardhj\IsotopeKlarnaCheckoutBundle\Dto\Item;
use Richardhj\IsotopeKlarnaCheckoutBundle\Dto\ShippingOption;
use Richardhj\IsotopeKlarnaCheckoutBundle\Dto\Surcharge;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiClient
{
    private NormalizerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function httpClient(Config $isoConfig): HttpClientInterface
    {
        $baseUri = 'https://api.klarna.com/';
        if ($isoConfig->klarna_api_test) {
            $baseUri = 'https://api.playground.klarna.com/';
        }

        return HttpClient::createForBaseUri($baseUri, [
            'auth_basic' => [$isoConfig->klarna_api_username, $isoConfig->klarna_api_password],
        ]);
    }

    public function orderLines(?Cart $cart): array
    {
        $orderLines = [];
        if (null === $cart) {
            return $orderLines;
        }

        foreach ($cart->getItems() as $item) {
            $itemDto = new Item($item, $cart);

            $orderLines[] = $this->serializer->normalize($itemDto);
        }

        foreach ($cart->getSurcharges() as $surcharge) {
            if (!$surcharge->addToTotal) {
                continue;
            }

            try {
                $surchargeDto = new Surcharge($surcharge);

                $orderLines[] = $this->serializer->normalize($surchargeDto);
            } catch (\Exception $e) {
            }
        }

        return $orderLines;
    }

    public function shippingOptions(?Cart $cart, array $shippingIds): array
    {
        if (empty($shippingIds)) {
            return [];
        }

        if (null !== $cart && null === Isotope::getCart()) {
            // An empty cart may be the case within a callback request.
            // Set cart to prevent errors within available check.
            Isotope::setCart($cart);
        }

        $methods = [];
        /** @var Shipping[] $shippingMethods */
        $shippingMethods = Shipping::findBy(['id IN ('.implode(',', $shippingIds).')', "enabled='1'"], null);
        if (null !== $shippingMethods) {
            foreach ($shippingMethods as $shippingMethod) {
                if (!$shippingMethod->isAvailable()) {
                    continue;
                }

                $methods[] = $shippingMethod;
            }

            if (false === $cart->hasShipping() || false === $cart->getShippingMethod()->isAvailable()) {
                // Set shipping method. This is what Klarna is doing as well.
                // Otherwise customers will be able to checkout without shipping fee!
                $cart->setShippingMethod($methods[0]);
                $cart->save();
            }
        }

        return array_map(fn (Shipping $shipping) => $this->serializer->normalize(new ShippingOption($shipping)), $methods);
    }

    public function updateAddressByApiResponse(Address $address, array $data): Address
    {
        $address->company = $data['organization_name'] ?? '';
        $address->firstname = $data['given_name'] ?? '';
        $address->lastname = $data['family_name'] ?? '';
        $address->email = $data['email'] ?? '';
        $address->salutation = $data['title'] ?? '';
        $address->street_1 = $data['street_address'] ?? '';
        $address->street_2 = $data['street_address2'] ?? '';
        $address->postal = $data['postal_code'] ?? '';
        $address->city = $data['city'] ?? '';
        $address->country = $data['country'] ?? '';

        $address->save();

        return $address;
    }

    public function getApiDataFromAddress(Address $address): array
    {
        return [
            'organization_name' => $address->company,
            'given_name' => $address->firstname,
            'family_name' => $address->lastname,
            'email' => $address->email,
            'title' => $address->salutation,
            'street_address' => $address->street_1,
            'street_address2' => $address->street_2,
            'postal_code' => $address->postal,
            'city' => $address->city,
            'country' => $address->country,
        ];
    }
}
