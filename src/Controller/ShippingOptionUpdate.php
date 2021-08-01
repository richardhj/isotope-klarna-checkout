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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\Model;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;
use Richardhj\IsotopeKlarnaCheckoutBundle\Api\ApiClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShippingOptionUpdate extends AbstractController
{
    private ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Will be called whenever the consumer selects a shipping option.
     * The response will contain the updated order_lines due of added shipping_fee.
     *
     * @param mixed $orderId
     */
    public function __invoke($orderId, Request $request): Response
    {
        $data = json_decode($request->getContent());
        if (null === $data) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        $this->initializeContaoFramework();

        /** @var Cart|Model $cart */
        $cart = Cart::findOneBy('klarna_order_id', $orderId);

        $shippingMethod = Shipping::findById($data->selected_shipping_option->id);
        $cart->setShippingMethod($shippingMethod);
        $cart->save();

        // Set cart to prevent errors within the Isotope logic.
        Isotope::setCart($cart);

        // Update order with updated shipping method
        $data->order_amount = round($cart->getTotal() * 100);
        $data->order_tax_amount = round(($cart->getTotal() - $cart->getTaxFreeTotal()) * 100);
        $data->order_lines = $this->client->orderLines($cart);

        return new JsonResponse($data);
    }
}
