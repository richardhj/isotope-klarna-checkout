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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Util;


use Contao\Model;
use Isotope\Interfaces\IsotopeShipping;
use Isotope\Model\Shipping;
use Isotope\Model\TaxClass;
use Isotope\Model\TaxRate;

final class ShippingOption
{

    const METHOD_PICK_UP_STORE = 'PickUpStore';
    const METHOD_HOME          = 'Home';
    const METHOD_BOX_REG       = 'BoxReg';
    const METHOD_BOX_UNREG     = 'BoxUnreg';
    const METHOD_PICK_UP_POINT = 'PickUpPoint';
    const METHOD_OWN           = 'Own';

    /**
     * @var IsotopeShipping|Shipping|Model
     */
    private $shipping;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $promo;

    /**
     * @var integer
     */
    public $price;

    /**
     * @var integer
     */
    public $tax_amount;

    /**
     * @var integer
     */
    public $tax_rate;

    /**
     * @var boolean
     */
    public $preselected;

    /**
     * @var string
     */
    public $shipping_method;

    /**
     * ShippingMethod constructor.
     *
     * @param IsotopeShipping $shipping
     */
    public function __construct(IsotopeShipping $shipping)
    {
        $this->shipping = $shipping;

        $this->processShippingMethod();
    }

    /**
     * @param IsotopeShipping $shipping
     *
     * @return ShippingOption
     */
    public static function createForShippingMethod(IsotopeShipping $shipping): ShippingOption
    {
        return new self($shipping);
    }

    /**
     * Fill properties by given shipping method model.
     */
    private function processShippingMethod()
    {
        $this->id              = $this->shipping->getId();
        $this->name            = $this->shipping->getLabel();
        $this->description     = strip_tags($this->shipping->getNote());
        $this->price           = $this->shipping->getPrice() * 100;
        $this->shipping_method = self::METHOD_OWN;

        if (0 !== $this->price) {
            if ($this->shipping->isPercentage()) {
                $this->name .= ' ('.$this->shipping->getPercentageLabel().')';
            }
        }

        $this->addTaxData();
    }

    /**
     * Add tax_amount and tax_rate.
     */
    private function addTaxData()
    {
        $this->tax_amount = 0;
        $this->tax_rate   = 0;

        try {
            /** @var TaxClass|Model $taxClass */
            $taxClass = $this->shipping->getRelated('tax_class');
            if (null === $taxClass) {
                return;
            }

            /** @var TaxRate|Model $includes */
            $includes = $taxClass->getRelated('includes');
            $rate = deserialize($includes->rate, true);

            $this->tax_rate   = $rate['value'] * 100;
            $this->tax_amount = $includes->calculateAmountIncludedInPrice($this->price);
        } catch (\Exception $e) {
            // :-/
        }
    }
}
