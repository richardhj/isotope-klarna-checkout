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

use Contao\Model;
use Isotope\Model\Address;

trait UpdateAddressTrait
{
    /**
     * @param Address|Model $address
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function updateAddressByApiResponse(Address $address, array $data): Address
    {
        $address->company = $data['organization_name'] ?? '';
        $address->firstname = $data['given_name'] ?? '';
        $address->lastname = $data['family_name'] ?? '';
        $address->email = $data['email'] ?? '';
        $address->salutation = $data['title'] ?? '';
        $address->street_1 = $data['street_address'] ?? '';
        $address->street_2 = $data['street_address2'] ?? '';
        $address->postal = $data['postal_code'] ?? '';
        $address->city = $data['city'] ?? '';
        $address->country = $data['country'] ?? '';

        $address->save();

        return $address;
    }

    private function getApiDataFromAddress(Address $address): array
    {
        return [
            'organization_name' => $address->company,
            'given_name' => $address->firstname,
            'family_name' => $address->lastname,
            'email' => $address->email,
            'title' => $address->salutation,
            'street_address' => $address->street_1,
            'street_address2' => $address->street_2,
            'postal_code' => $address->postal,
            'city' => $address->city,
            'country' => $address->country,
        ];
    }
}
