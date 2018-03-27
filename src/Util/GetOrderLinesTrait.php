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
use Isotope\Model\ProductCollection\Cart;

trait GetOrderLinesTrait
{

    /**
     * @var Cart|Model
     */
    private $cart;

    /**
     * Return the items in the cart as api-conform array.
     *
     * @return array
     */
    private function orderLines(): array
    {
        $return = [];

        if (null === $this->cart) {
            return [];
        }

        foreach ($this->cart->getItems() as $item) {
            $return[] = get_object_vars(OrderLine::createFromItem($item, $this->cart));
        }

        foreach ($this->cart->getSurcharges() as $surcharge) {
            if ($surcharge->addToTotal && null !== $orderLine = OrderLine::createForSurcharge($surcharge)) {
                $return[] = get_object_vars($orderLine);
            }
        }

        return $return;
    }
}
