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
use Contao\ModuleModel;
use Isotope\Model\Address;
use Isotope\Model\ProductCollection\Cart;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetShippingOptionsTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\UpdateAddressTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AddressUpdate
{

    use GetOrderLinesTrait;
    use GetShippingOptionsTrait;
    use UpdateAddressTrait;

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

        $shippingAddress = $data->shipping_address;

        // FIXME this is ambigue as Klarna does not submit the order_id
        /** @var Cart|Model $cart */
        $this->cart = Cart::findOneBy(
            ['type=?', 'total=?', 'currency=?'],
            ['cart', $data->order_amount / 100, $data->purchase_currency],
            ['order' => 'tstamp DESC']
        );

        $checkoutModule = ModuleModel::findById($this->cart->klarna_checkout_module);

        $address = $this->cart->getShippingAddress();
        $address = $address ?? Address::createForProductCollection($this->cart);
        $address = $this->updateAddressByApiResponse($address, (array)$shippingAddress);

        $this->cart->setShippingAddress($address);
        $this->cart->save();

        // Since we updated the shipping address, now we can fetch the current shipping methods.
        $shippingOptions = $this->shippingOptions(deserialize($checkoutModule->iso_shipping_modules, true));
        if ([] === $shippingOptions) {
            $response = new JsonResponse(['error_type' => 'unsupported_shipping_address']);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit;
        }

        // Update order since shipping method may get updated
        $data->shipping_options = $shippingOptions;
        $data->order_amount     = $this->cart->getTotal() * 100;
        $data->order_tax_amount = ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100;
        $data->order_lines      = $this->orderLines();

        $response = new JsonResponse($data);
        $response->send();
    }
}
