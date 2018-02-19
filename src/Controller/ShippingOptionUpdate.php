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


use Contao\Model;
use Contao\PageError404;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

class ShippingOptionUpdate
{

    use GetOrderLinesTrait;

    /**
     * Will be called whenever the consumer selects a shipping option.
     * The response will contain the updated order_lines due of added shipping_fee.
     *
     * @return void
     *
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __invoke()
    {
        $data = json_decode(file_get_contents('php://input'));
        if (null === $data) {
            $objHandler = new $GLOBALS['TL_PTY']['error_404']();
            /** @var PageError404 $objHandler */
            $response = $objHandler->getResponse();
            $response->send();
            exit;
        }

        // FIXME this is ambigue as Klarna does not submit the order_id
        /** @var Cart|Model $cart */
        $this->cart = Cart::findOneBy(
            ['type=?', 'currency=?'],
            ['cart', $data->purchase_currency],
            ['order' => 'tstamp DESC']
        );

        $shippingMethod = Shipping::findByIdOrAlias($data->selected_shipping_option->id);
        $this->cart->setShippingMethod($shippingMethod);
        $this->cart->save();

        // Update order with updated shipping method
        $data->order_amount     = $this->cart->getTotal() * 100;
        $data->order_tax_amount = ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100;
        $data->order_lines      = $this->orderLines();

        $response = new JsonResponse($data);
        $response->send();
    }
}
