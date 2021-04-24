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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\HookListener;

use Isotope\Interfaces\IsotopeOrderableCollection;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\ProductCollectionSurcharge;

class FindSurchargesForCollectionListener
{
    /**
     * Get shipping and payment surcharges for given collection.
     *
     * @return ProductCollectionSurcharge[]
     */
    public function findShippingAndPaymentSurcharges(IsotopeProductCollection $collection): array
    {
        if (!$collection instanceof IsotopeOrderableCollection) {
            return [];
        }

        // DO ADD shipping and payment surcharge to cart,
        // that's why we have this additional hook listener.
        if (!$collection instanceof Cart || null === $collection->klarna_order_id) {
            return [];
        }

        $surcharges = [];

        if (($surcharge = $collection->getShippingSurcharge()) !== null) {
            $surcharges[] = $surcharge;
        }

        if (($surcharge = $collection->getPaymentSurcharge()) !== null) {
            $surcharges[] = $surcharge;
        }

        return $surcharges;
    }
}
