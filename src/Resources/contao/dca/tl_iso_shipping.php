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

$GLOBALS['TL_DCA']['tl_iso_shipping']['palettes']['flat'] .= ';{klarna_legend},klarna_shipping_method';

$GLOBALS['TL_DCA']['tl_iso_shipping']['fields']['klarna_shipping_method'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_method'],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => [
        ShippingOption::METHOD_PICK_UP_STORE,
        ShippingOption::METHOD_HOME,
        ShippingOption::METHOD_BOX_REG,
        ShippingOption::METHOD_BOX_UNREG,
        ShippingOption::METHOD_PICK_UP_POINT,
        ShippingOption::METHOD_POSTAL,
        ShippingOption::METHOD_DHL_PACKSTATION,
        ShippingOption::METHOD_DIGITAL,
    ],
    'reference' => &$GLOBALS['TL_LANG']['tl_iso_shipping']['klarna_shipping_methods'],
    'eval'      => [
        'tl_class'           => 'w50',
        'includeBlankOption' => true,
    ],
    'sql'       => "varchar(64) NOT NULL default ''",
];
