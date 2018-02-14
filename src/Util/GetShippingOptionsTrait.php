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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Util;


use Contao\Model;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;

trait GetShippingOptionsTrait
{

    /**
     * @var Cart|Model
     */
    private $cart;

    /**
     * Get the shipping options as api-conform array.
     *
     * @param array $shippingIds The ids of the shipping methods allowed.
     *
     * @return array
     */
    private function shippingOptions(array $shippingIds): array
    {
        if (empty($shippingIds) || !\is_array($shippingIds)) {
            return [];
        }

        if (null !== $this->cart && null === Isotope::getCart()) {
            // An empty cart may be the case within a callback request.
            // Set cart to prevent errors within available check.
            Isotope::setCart($this->cart);
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

            // Add the default shipping address to the cart
            if (1 === \count($methods)) {
                $this->cart->setShippingMethod($methods[0]);
            }
        }

        return array_map(
            function (Shipping $shipping) {
                return get_object_vars(ShippingOption::createForShippingMethod($shipping));
            },
            $methods
        );
    }
}
