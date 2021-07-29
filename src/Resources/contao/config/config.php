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

use Richardhj\IsotopeKlarnaCheckoutBundle\HookListener\FindSurchargesForCollectionListener;

$GLOBALS['ISO_HOOKS']['findSurchargesForCollection'][] = [FindSurchargesForCollectionListener::class, 'findShippingAndPaymentSurcharges'];
