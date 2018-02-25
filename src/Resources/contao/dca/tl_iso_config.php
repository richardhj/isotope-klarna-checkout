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

$GLOBALS['TL_DCA']['tl_iso_config']['palettes']['__selector__'][] = 'use_klarna';

$GLOBALS['TL_DCA']['tl_iso_config']['palettes']['default'] .= ';{klarna_legend},use_klarna';

$GLOBALS['TL_DCA']['tl_iso_config']['subpalettes']['use_klarna'] = 'klarna_api_username,klarna_api_password,klarna_api_test';

$GLOBALS['TL_DCA']['tl_iso_config']['fields']['use_klarna'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_iso_config']['use_klarna'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => [
        'tl_class'       => 'w50',
        'submitOnChange' => true,
    ],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_iso_config']['fields']['klarna_api_username'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_iso_config']['klarna_api_username'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'mandatory' => true,
        'tl_class'  => 'w50',
    ],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_iso_config']['fields']['klarna_api_password'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_iso_config']['klarna_api_password'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'mandatory' => true,
        'tl_class'  => 'w50',
    ],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_iso_config']['fields']['klarna_api_test'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_iso_config']['klarna_api_test'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => [
        'tl_class' => 'w50',
    ],
    'sql'       => "char(1) NOT NULL default ''",
];
