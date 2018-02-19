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


use Contao\PageError404;
use Contao\System;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Cart as IsotopeCart;
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\CanCheckoutTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderValidation
{

    use CanCheckoutTrait;

    /**
     * Will be called before completing the purchase to validate the information provided by the consumer in Klarna's
     * Checkout iframe.
     * The response will redirect to an error page if the preCheckout hook will fail.
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

        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $data->order_id);
        if (null !== $isotopeOrder) {
            if (!$isotopeOrder->isLocked()) {
                $isotopeOrder->lock();
            }

            $response = new JsonResponse($data);
            $response->send();
            exit;
        }

        $this->cart = IsotopeCart::findOneBy('klarna_order_id', $data->order_id);
        Isotope::setCart($this->cart);

        // Create order
        $isotopeOrder                  = $this->cart->getDraftOrder();
        $isotopeOrder->klarna_order_id = $data->order_id;

        if (false === $this->checkPreCheckoutHook($isotopeOrder) || false === $this->canCheckout()) {
            $response = new JsonResponse(
                [
                    'error_type' => 'address_error',
                    //'error_text' => $this->translator->trans('ERR.orderFailed', null, $data->locale),
                    'error_text' => $GLOBALS['TL_LANG']['ERR']['orderFailed'],
                ]
            );
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->send();
            exit;
        }

        $isotopeOrder->lock();

        $response = new JsonResponse($data);
        $response->send();
    }

    /**
     * Call the pre checkout hook.
     * As of the default logic, a `false` return value requires to cancel the order.
     *
     * @param IsotopeOrder $order
     *
     * @return bool
     */
    private function checkPreCheckoutHook(IsotopeOrder $order): bool
    {
        if (isset($GLOBALS['ISO_HOOKS']['preCheckout']) && \is_array($GLOBALS['ISO_HOOKS']['preCheckout'])) {
            foreach ($GLOBALS['ISO_HOOKS']['preCheckout'] as $callback) {
                try {
                    return System::importStatic($callback[0])->{$callback[1]}($order, $this);
                } catch (\Exception $e) {
                    // The callback most probably required $this to be an instance of \Isotope\Module\Checkout.
                    // Nothing we can do about it here.
                }
            }
        }

        // Don't cancel the order per default.
        return true;
    }
}
