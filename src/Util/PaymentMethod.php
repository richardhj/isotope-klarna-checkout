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
use Contao\StringUtil;
use Isotope\Interfaces\IsotopePayment;
use Isotope\Model\Payment;

final class PaymentMethod
{

    /**
     * @var IsotopePayment|Payment|Model
     */
    private $payment;

    /**
     * Mandatory name.
     *
     * @var string
     */
    public $name;

    /**
     * Mandatory absolute HTTPS url for completing the purchase.
     *
     * @var string
     */
    public $redirect_url;

    /**
     * Optional HTTPS uri with image of payment method.
     *
     * @var string
     */
    public $image_uri;

    /**
     * Optional fee added to the order in minor units.
     *
     * @var int
     */
    public $fee;

    /**
     * Optional description with 500 character limit. Links can be set with the Markdown syntax [Text](URL).
     *
     * @var string
     */
    public $description;

    /**
     * If specified, limits the method to the listed countries (alpha 2 codes).
     *
     * @var array
     */
    public $countries;


    /**
     * PaymentMethod constructor.
     *
     * @param IsotopePayment $payment
     */
    public function __construct(IsotopePayment $payment)
    {
        $this->payment = $payment;

        $this->processPaymentMethod();
    }

    /**
     * @param IsotopePayment $paymentMethod
     *
     * @param string         $redirectUrl
     *
     * @return PaymentMethod
     */
    public static function createForPaymentMethod(IsotopePayment $paymentMethod, string $redirectUrl): PaymentMethod
    {
        $payment = new self($paymentMethod);

        $payment->redirect_url = $redirectUrl;

        return $payment;
    }

    /**
     * Fill properties by given payment method model.
     */
    private function processPaymentMethod(): void
    {
        $this->name        = $this->payment->name;
        $this->fee         = (int) round($this->payment->getPrice() * 100, 0);
        $this->description = $this->payment->note;

        $countries = StringUtil::deserialize($this->payment->countries, true);
        if ([] !== $countries) {
            $this->countries = $countries;
        }
    }
}
