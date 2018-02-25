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

use Contao\Input;
use Richardhj\IsotopeKlarnaCheckoutBundle\Controller\Push;

define(TL_MODE, 'FE');
require '../../../initialize.php';

$push = new Push();
$push(Input::get('klarna_order_id'));
