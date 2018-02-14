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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\UtilEntity;


use Contao\Environment;
use Contao\Model;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Model\Product;
use Isotope\Model\ProductCollectionItem;
use Isotope\Model\ProductCollectionSurcharge;
use Isotope\Model\ProductType;

final class OrderLine
{

    const TYPE_PHYSICAL     = 'physical';
    const TYPE_DISCOUNT     = 'discount';
    const TYPE_SHIPPING_FEE = 'shipping_fee';
    const TYPE_SALES_TAX    = 'sales_tax';
    const TYPE_DIGITAL      = 'digital';
    const TYPE_GIFT_CARD    = 'gift_card';
    const TYPE_STORE_CREDIT = 'store_credit';
    const TYPE_SURCHARGE    = 'surcharge';

    /**
     * @var ProductCollectionItem|Model
     */
    private $item;

    /**
     * @var ProductCollectionSurcharge
     */
    private $surcharge;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $reference;

    /**
     * @var string
     */
    public $name;

    /**
     * @var integer
     */
    public $quantity;

    /**
     * @var string
     */
    public $quantity_unit;

    /**
     * @var integer
     */
    public $unit_price;

    /**
     * @var integer
     */
    public $tax_rate;

    /**
     * @var integer
     */
    public $total_amount;

    /**
     * @var integer
     */
    public $total_discount_amount;

    /**
     * @var integer
     */
    public $total_tax_amount;

    /**
     * @var string
     */
    public $merchant_data;

    /**
     * @var string
     */
    public $product_url;

    /**
     * @var string
     */
    public $image_url;

    /**
     * @var \stdClass
     */
    public $product_identifiers;

    /**
     * @param Model|ProductCollectionItem $item
     */
    public function setItem($item)
    {
        $this->item = $item;
    }

    /**
     * @param ProductCollectionSurcharge $surcharge
     */
    public function setSurcharge(ProductCollectionSurcharge $surcharge)
    {
        $this->surcharge = $surcharge;
    }

    /**
     * @param ProductCollectionItem $item
     *
     * @return OrderLine
     */
    public static function createFromItem(ProductCollectionItem $item): OrderLine
    {
        $self = new self();
        $self->setItem($item);
        $self->processItem();

        return $self;
    }

    /**
     * @param ProductCollectionSurcharge $surcharge
     *
     * @return OrderLine
     */
    public static function createForSurcharge(ProductCollectionSurcharge $surcharge): OrderLine
    {
        $self = new self();
        $self->setSurcharge($surcharge);
        $self->processSurcharge();

        return $self;
    }

    /**
     * Fill properties by given item.
     */
    private function processItem()
    {
        $this->reference        = $this->item->getSku();
        $this->name             = $this->item->getName();
        $this->quantity         = $this->item->quantity;
        $this->unit_price       = $this->item->getPrice() * 100;
        $this->total_amount     = $this->item->getTotalPrice() * 100;
        $this->total_tax_amount = $this->item->getTotalPrice() - $this->item->getTaxFreeTotalPrice();
        $this->tax_rate         = ($this->total_tax_amount / $this->total_amount) * 1000;

        $this->addTypeForItem();
        $this->addProductUrlForItem();
        $this->addImageUrlForItem();
    }

    /**
     * Fill properties by given surcharge.
     */
    private function processSurcharge()
    {
        $this->reference    = $this->surcharge->id;
        $this->name         = $this->surcharge->label;
        $this->quantity     = 1;
        $this->unit_price   = $this->surcharge->total_price * 100;
        $this->total_amount = $this->surcharge->total_price * 100;

        if ($this->surcharge->hasTax()) {
            $this->total_tax_amount = ($this->surcharge->total_price - $this->surcharge->tax_free_total_price) * 100;
            $this->tax_rate         = ($this->total_tax_amount / $this->total_amount) * 1000;
        } else {
            $this->tax_rate         = 0;
            $this->total_tax_amount = 0;
        }

        switch (true) {
            case $this->surcharge instanceof ProductCollectionSurcharge\Shipping:
                $this->type = self::TYPE_SHIPPING_FEE;
                $this->name = $this->name ?: 'Shipping';
                break;

            case $this->surcharge instanceof ProductCollectionSurcharge\Tax:
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

    /**
     * Add the product type.
     * Physical by default, digital when product has downloads.
     */
    private function addTypeForItem()
    {
        $this->type = self::TYPE_PHYSICAL;

        try {
            /** @var IsotopeProduct|Product|Model $product */
            $product = $this->item->getProduct();
            /** @var ProductType|Model $productType */
            $productType = $product->getRelated('type');
            if ($productType->shipping_exempt && $productType->hasDownloads()) {
                $this->type = self::TYPE_DIGITAL;
            }
        } catch (\Exception $e) {
            // :-/
        }
    }

    /**
     * Add product url.
     */
    private function addProductUrlForItem()
    {
        try {
            $product = $this->item->getProduct();
            $jumpTo  = $this->item->getRelated('jumpTo');
            if (null !== $jumpTo && $this->item->hasProduct() && $product->isAvailableInFrontend()) {
                $this->product_url = Environment::get('url').'/'.$product->generateUrl($jumpTo);
            }
        } catch (\Exception $e) {
            // :-/
        }
    }

    /**
     * Add image url.
     */
    private function addImageUrlForItem()
    {
        $product = $this->item->getProduct();
        $images  = deserialize($product->images, true);
        if (!empty($images)) {
            $src = $images[0]['src'];

            // File without path must be located in the isotope root folder
            if (false === strpos($src, '/')) {
                $src = 'isotope/'.strtolower($src[0]).'/'.$src;
            }

            $this->image_url = Environment::get('url').'/'.$src;
        }
    }
}
