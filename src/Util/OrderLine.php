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


use Contao\Environment;
use Contao\Model;
use Contao\System;
use Doctrine\DBAL\Connection;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Interfaces\IsotopeProductCollection;
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
     * @var IsotopeProductCollection
     */
    private $collection;

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
     * @var Connection
     */
    private $connection;

    /**
     * OrderLine constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

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
     * @param IsotopeProductCollection $collection
     */
    public function setCollection(IsotopeProductCollection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * @param ProductCollectionItem    $item
     * @param IsotopeProductCollection $collection
     *
     * @return OrderLine
     */
    public static function createFromItem(ProductCollectionItem $item, IsotopeProductCollection $collection): OrderLine
    {
        /** @var Connection $connection */
        $connection = System::getContainer()->get('database_connection');

        $self = new self($connection);
        $self->setItem($item);
        $self->setCollection($collection);
        $self->processItem();

        return $self;
    }

    /**
     * @param ProductCollectionSurcharge $surcharge
     *
     * @return OrderLine|null
     */
    public static function createForSurcharge(ProductCollectionSurcharge $surcharge)
    {
        /** @var Connection $connection */
        $connection = System::getContainer()->get('database_connection');

        $self = new self($connection);
        $self->setSurcharge($surcharge);

        if ($surcharge instanceof ProductCollectionSurcharge\Rule && 'subtotal' !== $surcharge->applyTo) {
            // In case that the rule applies to the product, we need to alter the $total_discount_amount instead
            return null;
        }

        $self->processSurcharge();

        return $self;
    }

    /**
     * Fill properties by given item.
     */
    private function processItem()
    {
        $this->reference = $this->item->getSku();
        $this->name      = $this->item->getName();
        $this->quantity  = $this->item->quantity;

        $this->addTotalDiscountAmountForItem();
        $this->unit_price   = round($this->item->getPrice() * 100);
        $this->total_amount = round(($this->item->getTotalPrice() - $this->total_discount_amount / 100) * 100);

        $this->addTaxRateForItem();
        $this->total_tax_amount = 0;
        if (0 !== $this->tax_rate) {
            $this->total_tax_amount =
                round($this->total_amount - $this->total_amount * 10000 / (10000 + $this->tax_rate));
        }

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
        $this->unit_price   = round($this->surcharge->total_price * 100);
        $this->total_amount = round($this->surcharge->total_price * 100);

        if ($this->surcharge->hasTax()) {
            $this->total_tax_amount =
                round(($this->surcharge->total_price - $this->surcharge->tax_free_total_price) * 100);
            $this->tax_rate         = round(($this->total_tax_amount / $this->total_amount) * 1000);
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
            if (null !== $jumpTo && null !== $product
                && $this->item->hasProduct()
                && $product->isAvailableInFrontend()) {
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
        if (null === $product) {
            return;
        }

        $images = deserialize($product->images, true);
        if (!empty($images)) {
            $src = $images[0]['src'];

            // File without path must be located in the isotope root folder
            if (false === strpos($src, '/')) {
                $src = 'isotope/'.strtolower($src[0]).'/'.$src;
            }

            $this->image_url = Environment::get('url').'/'.$src;
        }
    }

    /**
     * Add tax_rate.
     */
    private function addTaxRateForItem()
    {
        $this->tax_rate = 0;

        try {
            $query = $this->connection->createQueryBuilder()
                ->select('r.rate')
                ->from('tl_iso_tax_rate', 'r')
                ->leftJoin('r', 'tl_iso_tax_class', 'c', 'c.includes=r.id')
                ->where('c.id=:tax_id')
                ->setParameter('tax_id', $this->item->tax_id)
                ->execute();

            $taxRate = $query->fetch(\PDO::FETCH_OBJ);
            if (null === $taxRate) {
                return;
            }

            $rate = deserialize($taxRate->rate, true);

            $this->tax_rate = round($rate['value'] * 100);
        } catch (\Exception $e) {
            // :-/
        }
    }

    /**
     * Add total_discount_amount by walking through the surcharges.
     */
    private function addTotalDiscountAmountForItem()
    {
        $surcharges = $this->collection->getSurcharges();
        foreach ($surcharges as $surcharge) {
            if (!$surcharge instanceof ProductCollectionSurcharge\Rule
                || ($surcharge->type === 'cart' && 'subtotal' === $surcharge->applyTo)) {
                continue;
            }

            // FIXME Error will come if rule wants to ADD fees. Value needs to be non-negative.
            $this->total_discount_amount += ($surcharge->getAmountForCollectionItem($this->item) * (-1));
        }

        $this->total_discount_amount = round($this->total_discount_amount * 100);
    }
}
