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
use Isotope\Model\Address;
use Isotope\Model\Config as IsotopeConfig;
use Isotope\Model\Product;
use Isotope\Model\ProductCollectionItem;
use Isotope\Model\ProductCollectionSurcharge;
use Isotope\Model\ProductCollectionSurcharge\Rule;
use Isotope\Model\ProductPrice;
use Isotope\Model\ProductType;
use Isotope\Model\TaxClass;
use Isotope\Model\TaxRate;

final class Item extends AbstractOrderLine
{
    public function __construct(ProductCollectionItem $item, IsotopeProductCollection $collection, Address $billingAddress, Address $shippingAddress)
    {
        $this->reference = $item->getSku();
        $this->name = $item->getName();
        $this->quantity = (int) $item->quantity;

        $this->addTotalDiscountAmount($collection->getSurcharges(), $item);
        $this->unit_price = (int) round($item->getPrice() * 100);
        $this->total_amount = (int) round(($item->getTotalPrice() - $this->total_discount_amount / 100) * 100);

        $this->addTaxRate($item, $collection, $billingAddress, $shippingAddress);
        $this->addTotalTaxAmount($item, $collection);

        $this->addType($item);
        $this->addProductUrl($item);
        $this->addImageUrl($item);
    }

    private function addTaxRate(ProductCollectionItem $item, IsotopeProductCollection $collection, Address $billingAddress, Address $shippingAddress)
    {
        // If config shows net prices, tax_rate stays 0 because sales taxes are added as surcharges.
        // If config shows gross prices, calculate tax_amount and tax_rate.
        if (IsotopeConfig::PRICE_DISPLAY_GROSS !== $collection->getConfig()->priceDisplay) {
            return;
        }

        /** @var ProductPrice&Model $price */
        $price = $item->getProduct()->getPrice();
        /** @var TaxClass&Model $taxClass */
        $taxClass = $price->getRelated('tax_class');
        if (null === $taxClass) {
            return;
        }

        /** @var TaxRate&Model $includedRate */
        $includedRate = $taxClass->getRelated('includes');
        $addresses = ['billing' => $billingAddress, 'shipping' => $shippingAddress];

        // Use the tax rate that is included in the price, if applicable
        if (null !== $includedRate
            && $includedRate->isApplicable($item->getTotalPrice(), $addresses)
            && $includedRate->isPercentage()) {
            $this->tax_rate = (int) round($includedRate->getAmount() * 100);

            return;
        }

        // Use the tax rate that is added to price, first applicable wins.
        // We purposely do not support cases when multiple tax rates are applicable to a single product,
        // or non-percantage tax_rates apply.
        if (null !== ($addedRates = $taxClass->getRelated('rates'))) {
            /** @var TaxRate $taxRate */
            foreach ($addedRates as $taxRate) {
                if ($taxRate->isApplicable($item->getTotalPrice(), $addresses) && $taxRate->isPercentage()) {
                    $this->tax_rate = (int) round($taxRate->getAmount() * 100);

                    return;
                }
            }
        }
    }

    private function addTotalTaxAmount(ProductCollectionItem $item, IsotopeProductCollection $collection)
    {
        // If config shows net prices, tax_rate stays 0 because sales taxes are added as surcharges.
        // If config shows gross prices, calculate tax_amount and tax_rate.
        if (IsotopeConfig::PRICE_DISPLAY_GROSS !== $collection->getConfig()->priceDisplay) {
            return;
        }

        $this->total_tax_amount = (int) ($this->total_amount - $this->total_amount * 10000 / (10000 + $this->tax_rate));
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
        /** @var ProductCollectionItem&Model $item */
        $product = $item->getProduct();
        $jumpTo = $item->getRelated('jumpTo');
        if (null !== $jumpTo
            && null !== $product
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
            /** @var ProductCollectionSurcharge&Model $surcharge */
            if (!$surcharge instanceof Rule || ('cart' === $surcharge->type && 'subtotal' === $surcharge->applyTo)) {
                continue;
            }

            $this->total_discount_amount += $surcharge->getAmountForCollectionItem($item) * (-1);
        }

        $this->total_discount_amount = (int) max(0, round($this->total_discount_amount * 100));
    }
}
