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

use Isotope\Model\ProductCollectionSurcharge;

final class Surcharge extends AbstractOrderLine
{
    public function __construct(ProductCollectionSurcharge $surcharge)
    {
        if ($surcharge instanceof ProductCollectionSurcharge\Rule && ('product' === $surcharge->type || 'subtotal' !== $surcharge->applyTo)) {
            // In case that the rule applies to the product, we need to alter the $total_discount_amount instead
            throw new \RuntimeException('This surcharge does not apply to the order but to a product');
        }

        $this->reference = (string) $surcharge->id;
        $this->name = $surcharge->label;
        $this->quantity = 1;
        $this->unit_price = (int) round($surcharge->total_price * 100);
        $this->total_amount = (int) round($surcharge->total_price * 100);

        if ($surcharge->hasTax()) {
            $this->total_tax_amount = (int) round(($surcharge->total_price - $surcharge->tax_free_total_price) * 100);
            $this->tax_rate = (int) round(($this->total_tax_amount / $this->total_amount) * 1000);
        } else {
            $this->tax_rate = 0;
            $this->total_tax_amount = 0;
        }

        switch (true) {
            case $surcharge instanceof ProductCollectionSurcharge\Shipping:
                $this->type = self::TYPE_SHIPPING_FEE;
                $this->name = $this->name ?: 'Shipping';
                break;

            case $surcharge instanceof ProductCollectionSurcharge\Tax:
                $this->type = self::TYPE_SALES_TAX;
                break;

            case $this->total_amount < 0:
                $this->type = self::TYPE_DISCOUNT;
                break;

            default:
                $this->type = self::TYPE_SURCHARGE;
                break;
        }
    }
}
