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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Util;

use Contao\Environment;
use Contao\Model;
use Contao\StringUtil;
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
    const TYPE_PHYSICAL = 'physical';
    const TYPE_DISCOUNT = 'discount';
    const TYPE_SHIPPING_FEE = 'shipping_fee';
    const TYPE_SALES_TAX = 'sales_tax';
    const TYPE_DIGITAL = 'digital';
    const TYPE_GIFT_CARD = 'gift_card';
    const TYPE_STORE_CREDIT = 'store_credit';
    const TYPE_SURCHARGE = 'surcharge';

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
     * @var int
     */
    public $quantity;

    /**
     * @var string
     */
    public $quantity_unit;

    /**
     * @var int
     */
    public $unit_price;

    /**
     * @var int
     */
    public $tax_rate;

    /**
     * @var int
     */
    public $total_amount;

    /**
     * @var int
     */
    public $total_discount_amount;

    /**
     * @var int
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
     * @var Connection
     */
    private $connection;

    /**
     * OrderLine constructor.
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

    public function setSurcharge(ProductCollectionSurcharge $surcharge)
    {
        $this->surcharge = $surcharge;
    }

    public function setCollection(IsotopeProductCollection $collection)
    {
        $this->collection = $collection;
    }

    public static function createFromItem(ProductCollectionItem $item, IsotopeProductCollection $collection): self
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
     * @return OrderLine|null
     */
    public static function createForSurcharge(ProductCollectionSurcharge $surcharge)
    {
        /** @var Connection $connection */
        $connection = System::getContainer()->get('database_connection');

        $self = new self($connection);
        $self->setSurcharge($surcharge);

        if ($surcharge instanceof ProductCollectionSurcharge\Rule
            && ('product' === $surcharge->type || 'subtotal' !== $surcharge->applyTo)) {
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
        $this->name = $this->item->getName();
        $this->quantity = $this->item->quantity;

        $this->addTotalDiscountAmountForItem();
        $this->unit_price = (int) round($this->item->getPrice() * 100, 0);
        $this->total_amount = (int) round(($this->item->getTotalPrice() - $this->total_discount_amount / 100) * 100, 0);

        $this->addTaxRateForItem();
        $this->total_tax_amount = 0;
        if (0 === $this->tax_rate) {
            // No distinct tax rate was found, maybe multiple taxes apply, simply calculate the tax_rate
            $taxFreePrice = (int) round($this->item->getTaxFreePrice() * 100, 0);
            $price = (int) round($this->item->getPrice() * 100, 0);

            if ($taxFreePrice > 0) {
                $taxRate = ($price - $taxFreePrice) / $taxFreePrice;

                $this->tax_rate = (int) round($taxRate * 100, 0);
            }
        }

        if (0 !== $this->tax_rate) {
            $this->total_tax_amount =
                (int) round($this->total_amount - $this->total_amount * 10000 / (10000 + $this->tax_rate), 0);
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
        $this->reference = $this->surcharge->id;
        $this->name = $this->surcharge->label;
        $this->quantity = 1;
        $this->unit_price = (int) round($this->surcharge->total_price * 100, 0);
        $this->total_amount = (int) round($this->surcharge->total_price * 100, 0);

        if ($this->surcharge->hasTax()) {
            $this->total_tax_amount =
                (int) round(($this->surcharge->total_price - $this->surcharge->tax_free_total_price) * 100, 0);
            $this->tax_rate = (int) round(($this->total_tax_amount / $this->total_amount) * 1000, 0);
        } else {
            $this->tax_rate = 0;
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
            $jumpTo = $this->item->getRelated('jumpTo');
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
    private function addTaxRateForItem(): void
    {
        $this->tax_rate = 0;

        try {
            $taxRate = $this->connection->createQueryBuilder()
                ->select('tax_rate.rate')
                ->from('tl_iso_tax_rate', 'tax_rate')
                ->innerJoin('tax_rate', 'tl_iso_tax_class', 'tax_class', 'tax_class.includes = tax_rate.id')
                ->where('tax_class.id = :tax_id')
                ->setParameter('tax_id', $this->item->tax_id)
                ->execute()
                ->fetchColumn();

            if (false === $taxRate) {
                $taxRate = $this->connection->createQueryBuilder()
                    ->select('tax_rate.rate')
                    ->from('tl_iso_product_price', 'price')
                    ->innerJoin('price', 'tl_iso_tax_class', 'tax_class', 'price.tax_class = tax_class.id')
                    ->innerJoin('tax_class', 'tl_iso_tax_rate', 'tax_rate', 'tax_class.includes = tax_rate.id')
                    ->where('price.pid = :product_id')
                    ->setParameter('product_id', $this->item->product_id)
                    ->execute()
                    ->fetchColumn();
            }

            if (false === $taxRate) {
                return;
            }

            $rate = StringUtil::deserialize($taxRate, true);

            $this->tax_rate = (int) round($rate['value'] * 100, 0);
        } catch (\Exception $e) {
            System::log($e->getMessage(), __METHOD__, TL_ERROR);
        }
    }

    /**
     * Add total_discount_amount by walking through the surcharges.
     */
    private function addTotalDiscountAmountForItem(): void
    {
        foreach ((array) $this->collection->getSurcharges() as $surcharge) {
            if (!$surcharge instanceof ProductCollectionSurcharge\Rule
                || ('cart' === $surcharge->type && 'subtotal' === $surcharge->applyTo)) {
                continue;
            }

            // FIXME Error will come if rule wants to ADD fees. Value needs to be non-negative.
            $this->total_discount_amount += ($surcharge->getAmountForCollectionItem($this->item) * (-1));
        }

        $this->total_discount_amount = (int) round($this->total_discount_amount * 100, 0);
    }
}
