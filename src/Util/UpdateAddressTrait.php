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
use Isotope\Model\Address;

trait UpdateAddressTrait
{

    /**
     * @param Address|Model $address
     * @param array         $data
     *
     * @return Address
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function updateAddressByApiResponse(Address $address, array $data): Address
    {
        $address->company    = $data['organization_name'];
        $address->firstname  = $data['given_name'];
        $address->lastname   = $data['family_name'];
        $address->email      = $data['email'];
        $address->salutation = $data['title'];
        $address->street_1   = $data['street_address'];
        $address->street_2   = $data['street_address2'];
        $address->postal     = $data['postal_code'];
        $address->city       = $data['city'];
        $address->country    = $data['country'];

        $address->save();

        return $address;
    }
}
