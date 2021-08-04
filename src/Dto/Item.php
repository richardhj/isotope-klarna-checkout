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

use Contao\Environment;
use Contao\Model;
use Contao\StringUtil;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\Config as IsotopeConfig;
use Isotope\Model\Product;
use Isotope\Model\ProductCollectionItem;
use Isotope\Model\ProductCollectionSurcharge\Rule;
use Isotope\Model\ProductType;

final class Item extends AbstractOrderLine
{
    public function __construct(ProductCollectionItem $item, IsotopeProductCollection $collection)
    {
        $this->reference = $item->getSku();
        $this->name = $item->getName();
        $this->quantity = (int) $item->quantity;

        $this->addTotalDiscountAmount($collection->getSurcharges(), $item);
        $this->unit_price = (int) round($item->getPrice() * 100);
        $this->total_amount = (int) round(($item->getTotalPrice() - $this->total_discount_amount / 100) * 100);

        // If config shows net prices, tax_rate stays 0 because sales taxes are added as surcharges.
        // If config shows gross prices, calculate tax_amount and tax_rate.
        if (IsotopeConfig::PRICE_DISPLAY_GROSS === $collection->getConfig()->priceDisplay) {
            $this->total_tax_amount = (int) round(($item->getTotalPrice() - $item->getTaxFreeTotalPrice()) * 100);
            $this->tax_rate = (int) round(($this->total_tax_amount / ($this->total_amount - $this->total_tax_amount) * 10000));
        }

        $this->addType($item);
        $this->addProductUrl($item);
        $this->addImageUrl($item);
    }

    private function addType(ProductCollectionItem $item)
    {
        $this->type = self::TYPE_PHYSICAL;

        /** @var IsotopeProduct|Product|Model $product */
        $product = $item->getProduct();
        /** @var ProductType|Model $productType */
        $productType = $product->getRelated('type');
        if ($productType->shipping_exempt && $productType->hasDownloads()) {
            $this->type = self::TYPE_DIGITAL;
        }
    }

    private function addProductUrl(ProductCollectionItem $item)
    {
        $product = $item->getProduct();
        $jumpTo = $item->getRelated('jumpTo');
        if (null !== $jumpTo && null !== $product
                && $item->hasProduct()
                && $product->isAvailableInFrontend()) {
            $this->product_url = Environment::get('url').'/'.$product->generateUrl($jumpTo);
        }
    }

    private function addImageUrl(ProductCollectionItem $item)
    {
        $product = $item->getProduct();
        if (null === $product) {
            return;
        }

        $images = StringUtil::deserialize($product->images, true);
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
     * Add total_discount_amount by walking through the surcharges.
     */
    private function addTotalDiscountAmount(array $surcharges, ProductCollectionItem $item): void
    {
        foreach ($surcharges as $surcharge) {
            if (!$surcharge instanceof Rule || ('cart' === $surcharge->type && 'subtotal' === $surcharge->applyTo)) {
                continue;
            }

            $this->total_discount_amount += $surcharge->getAmountForCollectionItem($item) * (-1);
        }

        $this->total_discount_amount = (int) max(0, round($this->total_discount_amount * 100));
    }
}
