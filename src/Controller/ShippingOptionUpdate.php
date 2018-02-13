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
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;
use Richardhj\IsotopeKlarnaCheckoutBundle\UtilEntity\OrderLine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ShippingOptionUpdate
{

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @param Request $request The request.
     *
     * @return void
     *
     * @throws PageNotFoundException If page is requested without data.
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __invoke(Request $request)
    {
        $data = json_decode($request->getContent());
        if (null === $data) {
            throw new PageNotFoundException('Page call not valid.');
        }

        // FIXME this is ambigue as Klarna does not submit the order_id
        /** @var Cart|Model $cart */
        $this->cart = Cart::findOneBy(
            ['type=?', 'total=?', 'currency=?'],
            ['cart', $data->order_amount / 100, $data->purchase_currency],
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


    /**
     * Return the items in the cart as api-conform array.
     *
     * @return array
     */
    private function orderLines(): array
    {
        $return = [];

        if (null === $this->cart) {
            return [];
        }

        foreach ($this->cart->getItems() as $item) {
            $return[] = get_object_vars(OrderLine::createFromItem($item));
        }

        foreach ($this->cart->getSurcharges() as $surcharge) {
            if ($surcharge->addToTotal) {
                $return[] = get_object_vars(OrderLine::createForSurcharge($surcharge));
            }
        }

        return $return;
    }
}
