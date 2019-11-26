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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller;


use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Model;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShippingOptionUpdate
{

    use GetOrderLinesTrait;

    /**
     * Will be called whenever the consumer selects a shipping option.
     * The response will contain the updated order_lines due of added shipping_fee.
     */
    public function __invoke($orderId, Request $request): Response
    {
        $data = json_decode($request->getContent());
        if (null === $data) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        /** @var Cart|Model $cart */
        $this->cart = Cart::findOneBy('klarna_order_id', $orderId);

        $shippingMethod = Shipping::findById($data->selected_shipping_option->id);
        $this->cart->setShippingMethod($shippingMethod);
        $this->cart->save();

        // Set cart to prevent errors within the Isotope logic.
        Isotope::setCart($this->cart);

        // Update order with updated shipping method
        $data->order_amount     = $this->cart->getTotal() * 100;
        $data->order_tax_amount = ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100;
        $data->order_lines      = $this->orderLines();

        return new JsonResponse($data);
    }
}
