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


use Contao\Model;
use Contao\ModuleModel;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Shipping;
use Richardhj\IsotopeKlarnaCheckoutBundle\UtilEntity\ShippingOption;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class AddressUpdate
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

        $shippingAddress = $data->shipping_address;

        $cart    = Isotope::getCart();
        $address = $cart->getShippingAddress();
        $address = $address ?? Address::createForProductCollection($cart);
        $address = $this->updateAddressByApiResponse($address, $shippingAddress);

        $cart->setShippingAddress($address);
        $cart->save();

        // Since we updated the shipping address, now we can fetch the current shipping methods.
        $shippingOptions = $this->shippingOptions();
        if ([] === $shippingOptions) {
            $response = new JsonResponse(['error_type' => 'unsupported_shipping_address']);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit;
        }

        $response = new JsonResponse(
            [
                'shipping_options' => $shippingOptions,
            ]
        );
        $response->send();
    }

    /**
     * @param Address|Model $address
     * @param object        $data
     *
     * @return Address
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function updateAddressByApiResponse(Address $address, object $data): Address
    {
        $address->company    = $data->organization_name;
        $address->firstname  = $data->given_name;
        $address->lastname   = $data->family_name;
        $address->email      = $data->email;
        $address->salutation = $data->title;
        $address->street_1   = $data->street_address;
        $address->street_2   = $data->street_address2;
        $address->postal     = $data->postal_code;
        $address->city       = $data->city;
        $address->country    = $data->country;

        $address->save();

        return $address;
    }

    /**
     * Get the shipping options as api-conform array.
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    private function shippingOptions(): array
    {
        $return = [];

        $session = new Session();
        $session->start();
        $checkoutModule = $session->get('ISO_CHECKOUT_MODULE');
        if (!$checkoutModule) {
            throw new \RuntimeException('Could not determine checkout module in use.');
        }

        $module = ModuleModel::findById($checkoutModule);
        $ids    = deserialize($module->iso_shipping_modules);
        if (empty($ids) || !\is_array($ids)) {
            return [];
        }

        /** @var Shipping[] $shippingMethods */
        $shippingMethods = Shipping::findBy(['id IN ('.implode(',', $ids).')', "enabled='1'"], null);
        if (null !== $shippingMethods) {
            foreach ($shippingMethods as $shippingMethod) {
                if (!$shippingMethod->isAvailable()) {
                    continue;
                }

                $return[] = (array)ShippingOption::createForShippingMethod($shippingMethod);
            }
        }

        return $return;
    }
}
