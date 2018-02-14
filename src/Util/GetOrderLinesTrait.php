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
            $return[] = get_object_vars(OrderLine::createFromItem($item));
        }

        $surcharges = $this->cart->getSurcharges();
        if (null !== ($paymentSurcharge = $this->cart->getShippingSurcharge())) {
            $surcharges[] = $paymentSurcharge;
        }
        if (null !== ($paymentSurcharge = $this->cart->getPaymentSurcharge())) {
            $surcharges[] = $paymentSurcharge;
        }

        foreach ($surcharges as $surcharge) {
            if ($surcharge->addToTotal) {
                $return[] = get_object_vars(OrderLine::createForSurcharge($surcharge));
            }
        }

        return $return;
    }
}
