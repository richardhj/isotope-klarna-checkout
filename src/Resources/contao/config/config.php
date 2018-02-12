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

use Richardhj\IsotopeKlarnaCheckoutBundle\Module\KlarnaCheckout;
use Richardhj\IsotopeKlarnaCheckoutBundle\Module\KlarnaCheckoutConfirmation;


$GLOBALS['FE_MOD']['isotope']['iso_klarna_checkout']              = KlarnaCheckout::class;
$GLOBALS['FE_MOD']['isotope']['iso_klarna_checkout_confirmation'] = KlarnaCheckoutConfirmation::class;
