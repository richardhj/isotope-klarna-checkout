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
use Isotope\Isotope;
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
     * Will be called whenever the consumer changes billing or shipping address.
     * The response contains the updated shipping options.
     *
     * @param integer $orderId The checkout order id.
     * @param Request $request The request.
     *
     * @return void
     *
     * @throws PageNotFoundException If page is requested without data.
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __invoke($orderId, Request $request)
    {
        $data = json_decode($request->getContent());
        if (null === $data) {
            throw new PageNotFoundException('Page call not valid.');
        }

        /** @var Cart|Model $cart */
        $this->cart = Cart::findOneBy('klarna_order_id', $orderId);

        $shippingAddress = $data->shipping_address;
        $checkoutModule  = ModuleModel::findById($this->cart->klarna_checkout_module);

        $address = $this->cart->getShippingAddress();
        $address = $address ?? Address::createForProductCollection($this->cart);
        $address = $this->updateAddressByApiResponse($address, (array)$shippingAddress);

        $this->cart->setShippingAddress($address);
        $this->cart->save();

        // Set cart to prevent errors within the Isotope logic.
        Isotope::setCart($this->cart);

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
