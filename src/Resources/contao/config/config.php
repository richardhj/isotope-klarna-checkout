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

use Richardhj\IsotopeKlarnaCheckoutBundle\Module\KlarnaCheckout;
use Richardhj\IsotopeKlarnaCheckoutBundle\Module\KlarnaCheckoutConfirmation;

$GLOBALS['FE_MOD']['isotope']['iso_klarna_checkout'] = KlarnaCheckout::class;
$GLOBALS['FE_MOD']['isotope']['iso_klarna_checkout_confirmation'] = KlarnaCheckoutConfirmation::class;

$GLOBALS['ISO_HOOKS']['findSurchargesForCollection'][] =
    ['richardhj.klarna_checkout.hook_listener.find_surcharges_for_collection', 'findShippingAndPaymentSurcharges'];
