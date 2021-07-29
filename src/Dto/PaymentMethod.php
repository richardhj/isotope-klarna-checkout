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

use Contao\StringUtil;
use Isotope\Interfaces\IsotopePayment;

final class PaymentMethod
{
    public string $name;
    public string $redirect_url;
    public ?string $image_uri = null;
    public int $fee = 0;
    public ?string $description = null;
    public ?array $countries = null;

    public function __construct(IsotopePayment $payment, string $redirectUrl)
    {
        $this->name = $payment->name;
        $this->fee = (int) round($payment->getPrice() * 100);
        $this->description = $payment->note;
        $this->redirect_url = $redirectUrl;

        $this->countries = StringUtil::deserialize($payment->countries) ?: null;
    }
}
