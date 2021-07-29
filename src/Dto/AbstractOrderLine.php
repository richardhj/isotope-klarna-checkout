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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Dto;

abstract class AbstractOrderLine
{
    const TYPE_PHYSICAL = 'physical';
    const TYPE_DISCOUNT = 'discount';
    const TYPE_SHIPPING_FEE = 'shipping_fee';
    const TYPE_SALES_TAX = 'sales_tax';
    const TYPE_DIGITAL = 'digital';
    const TYPE_GIFT_CARD = 'gift_card';
    const TYPE_STORE_CREDIT = 'store_credit';
    const TYPE_SURCHARGE = 'surcharge';

    public ?string $type = null;
    public ?string $reference = null;
    public ?string $name = null;
    public int $quantity = 1;
    public ?string $quantity_unit = null;
    public int $unit_price = 0;
    public int $tax_rate = 0;
    public int $total_amount = 0;
    public int $total_discount_amount = 0;
    public int $total_tax_amount = 0;
    public ?string $merchant_data;
    public ?string $product_url = null;
    public ?string $image_url = null;
    public ?object $product_identifiers = null;
}
