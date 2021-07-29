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

use Contao\Model;
use Contao\StringUtil;
use Isotope\Interfaces\IsotopeShipping;
use Isotope\Model\TaxClass;
use Isotope\Model\TaxRate;

final class ShippingOption
{
    const METHOD_PICK_UP_STORE = 'PickUpStore';
    const METHOD_HOME = 'Home';
    const METHOD_BOX_REG = 'BoxReg';
    const METHOD_BOX_UNREG = 'BoxUnreg';
    const METHOD_PICK_UP_POINT = 'PickUpPoint';
    const METHOD_OWN = 'Own';
    const METHOD_POSTAL = 'Postal';
    const METHOD_DHL_PACKSTATION = 'DHLPackstation';
    const METHOD_DIGITAL = 'Digital';

    public ?int $id = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $promo = null;
    public int $price = 0;
    public int $tax_amount = 0;
    public int $tax_rate = 0;
    public bool $preselected = false;
    public ?string $shipping_method = null;
    public ?array $billing_countries = null;

    public function __construct(IsotopeShipping $shipping)
    {
        $this->id = (int) $shipping->getId();
        $this->name = $shipping->getLabel();
        $this->description = strip_tags((string) $shipping->getNote());
        $this->price = (int) round($shipping->getPrice() * 100);
        $this->shipping_method = $shipping->klarna_shipping_method ?: self::METHOD_OWN;
        $this->billing_countries = StringUtil::deserialize($shipping->countries);

        if (0 !== $this->price && $shipping->isPercentage()) {
            $this->name .= ' ('.$shipping->getPercentageLabel().')';
        }

        // Add tax_amount and tax_rate
        $this->addTaxData($shipping);
    }

    private function addTaxData(IsotopeShipping $shipping)
    {
        /** @var TaxClass|Model $taxClass */
        $taxClass = $shipping->getRelated('tax_class');
        if (null === $taxClass) {
            return;
        }

        /** @var TaxRate|Model $includes */
        $includes = $taxClass->getRelated('includes');
        $rate = StringUtil::deserialize($includes->rate, true);

        $this->tax_rate = (int) round($rate['value'] * 100);
        $this->tax_amount = (int) round($includes->calculateAmountIncludedInPrice($this->price) * 100);
    }
}
