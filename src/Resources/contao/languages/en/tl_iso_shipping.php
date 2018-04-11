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


use Richardhj\IsotopeKlarnaCheckoutBundle\Util\ShippingOption;

$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_legend'] = 'Klarna';

$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_method'][0] = 'Shipping type';
$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_method'][1] = 'Classify this shipping method by Klarna provided categories (if any matches).';

$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_methods'][ShippingOption::METHOD_PICK_UP_STORE] = 'Pick up store';
$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_methods'][ShippingOption::METHOD_HOME]          = 'Home delivery';
$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_methods'][ShippingOption::METHOD_BOX_REG]       = 'Registered box';
$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_methods'][ShippingOption::METHOD_BOX_UNREG]     = 'Unregistered box';
$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_methods'][ShippingOption::METHOD_PICK_UP_POINT] = 'Pick up point';
