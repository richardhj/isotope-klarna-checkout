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

$GLOBALS['TL_DCA']['tl_iso_config']['palettes']['iso_klarna_checkout'] = '{title_legend},name,headline,type;'
                                                                         .'{config_legend},iso_shipping_modules,nc_notification;'
                                                                         .'{redirect_legend},klarna_terms_page,klarna_checkout_page,klarna_confirmation_page,klarna_cancellation_page;'
                                                                         .'{template_legend},customTpl;'
                                                                         .'{protected_legend:hide},protected;'
                                                                         .'{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_terms_page'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['klarna_terms_page'],
    'exclude'    => true,
    'inputType'  => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval'       => [
        'mandatory' => true,
        'fieldType' => 'radio',
        'tl_class'  => 'clr',
    ],
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => [
        'type' => 'hasOne',
        'load' => 'lazy',
    ],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_checkout_page'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['klarna_checkout_page'],
    'exclude'    => true,
    'inputType'  => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval'       => [
        'mandatory' => true,
        'fieldType' => 'radio',
        'tl_class'  => 'clr',
    ],
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => [
        'type' => 'hasOne',
        'load' => 'lazy',
    ],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_confirmation_page'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['klarna_confirmation_page'],
    'exclude'    => true,
    'inputType'  => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval'       => [
        'mandatory' => true,
        'fieldType' => 'radio',
        'tl_class'  => 'clr',
    ],
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => [
        'type' => 'hasOne',
        'load' => 'lazy',
    ],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_cancellation_page'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['klarna_cancellation_page'],
    'exclude'    => true,
    'inputType'  => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval'       => [
        'fieldType' => 'radio',
        'tl_class'  => 'clr',
    ],
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => [
        'type' => 'hasOne',
        'load' => 'lazy',
    ],
];
