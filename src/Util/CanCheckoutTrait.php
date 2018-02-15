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

trait CanCheckoutTrait
{

    /**
     * @var Cart|Model
     */
    private $cart;

    /**
     * Check if the checkout can be executed
     *
     * @return bool
     */
    protected function canCheckout(): bool
    {
        return false === $this->cart->isEmpty() && false === $this->cart->hasErrors();
    }
}
