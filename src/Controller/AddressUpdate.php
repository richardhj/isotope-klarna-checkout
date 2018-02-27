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
use Contao\ModuleModel;
use Contao\PageError404;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\ProductCollection\Cart;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetShippingOptionsTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\UpdateAddressTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     *
     * @return void
     *
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __invoke($orderId)
    {
        $data = json_decode(file_get_contents('php://input'));
        if (null === $data) {
            global $objPage;

            $objHandler = new $GLOBALS['TL_PTY']['error_404']();
            /** @var PageError404 $objHandler */
            $objHandler->generate($objPage->id);
            exit;
        }

        /** @var Cart|Model $cart */
        $this->cart = Cart::findOneBy('klarna_order_id', $orderId);

        $shippingAddress = $data->shipping_address;
        $checkoutModule  = ModuleModel::findById($this->cart->klarna_checkout_module);
        if (null === $checkoutModule) {
            $this->errorResponse();
        }

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
            $this->errorResponse();
        }

        // Update order since shipping method may get updated
        $data->shipping_options = $shippingOptions;
        $data->order_amount     = $this->cart->getTotal() * 100;
        $data->order_tax_amount = ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100;
        $data->order_lines      = $this->orderLines();

        $response = new JsonResponse($data);
        $response->send();
    }

    /**
     * Send a response that will display an error to the customer.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function errorResponse()
    {
        $response = new JsonResponse(['error_type' => 'unsupported_shipping_address']);
        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $response->send();
        exit;
    }
}
