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

$GLOBALS['TL_DCA']['tl_module']['palettes']['iso_klarna_checkout'] = '{title_legend},name,headline,type;'
                                                                     .'{config_legend},iso_shipping_modules,iso_payment_modules,nc_notification,iso_addToAddressbook,klarna_show_subtotal_detail;'
                                                                     .'{redirect_legend},iso_cart_jumpTo,klarna_terms_page,klarna_checkout_page,klarna_confirmation_page,klarna_cancellation_page;'
                                                                     .'{customization_legend:hide},klarna_color_button,klarna_color_button_text,klarna_color_checkbox,klarna_color_checkbox_checkmark,klarna_color_header,klarna_color_link;'
                                                                     .'{template_legend},customTpl;'
                                                                     .'{protected_legend:hide},protected;'
                                                                     .'{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['iso_klarna_checkout_confirmation'] = '{title_legend},name,headline,type;'
                                                                                  .'{config_legend},nc_notification,iso_addToAddressbook;'
                                                                                  .'{template_legend},customTpl;'
                                                                                  .'{protected_legend:hide},protected;'
                                                                                  .'{expert_legend:hide},guests,cssID';

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_show_subtotal_detail'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['klarna_show_subtotal_detail'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => [
        'tl_class' => 'w50 m12',
    ],
    'sql'       => "char(1) NOT NULL default ''",
];

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

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_color_button'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['klarna_color_button'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'minlength'      => 6,
        'maxlength'      => 6,
        'colorpicker'    => true,
        'isHexColor'     => true,
        'decodeEntities' => true,
        'tl_class'       => 'w50 wizard',
    ],
    'sql'       => "varchar(6) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_color_button_text'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['klarna_color_button_text'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'minlength'      => 6,
        'maxlength'      => 6,
        'colorpicker'    => true,
        'isHexColor'     => true,
        'decodeEntities' => true,
        'tl_class'       => 'w50 wizard',
    ],
    'sql'       => "varchar(6) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_color_checkbox'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['klarna_color_checkbox'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'minlength'      => 6,
        'maxlength'      => 6,
        'colorpicker'    => true,
        'isHexColor'     => true,
        'decodeEntities' => true,
        'tl_class'       => 'w50 wizard',
    ],
    'sql'       => "varchar(6) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_color_checkbox_checkmark'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['klarna_color_checkbox_checkmark'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'minlength'      => 6,
        'maxlength'      => 6,
        'colorpicker'    => true,
        'isHexColor'     => true,
        'decodeEntities' => true,
        'tl_class'       => 'w50 wizard',
    ],
    'sql'       => "varchar(6) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_color_header'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['klarna_color_header'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'minlength'      => 6,
        'maxlength'      => 6,
        'colorpicker'    => true,
        'isHexColor'     => true,
        'decodeEntities' => true,
        'tl_class'       => 'w50 wizard',
    ],
    'sql'       => "varchar(6) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['klarna_color_link'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['klarna_color_link'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'minlength'      => 6,
        'maxlength'      => 6,
        'colorpicker'    => true,
        'isHexColor'     => true,
        'decodeEntities' => true,
        'tl_class'       => 'w50 wizard',
    ],
    'sql'       => "varchar(6) NOT NULL default ''",
];
