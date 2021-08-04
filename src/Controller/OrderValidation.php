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
use Contao\Module;
use Contao\ModuleModel;
use Contao\System;
use Isotope\Interfaces\IsotopeOrderableCollection;
use Isotope\Isotope;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\ProductCollection\Cart as IsotopeCart;
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Isotope\Module\Checkout as CheckoutModule;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderValidation extends AbstractController
{
    /**
     * Will be called before completing the purchase to validate the information provided by the consumer in Klarna's
     * Checkout iframe.
     * The response will redirect to an error page if the preCheckout hook will fail.
     */
    public function __invoke(Request $request): Response
    {
        $data = json_decode($request->getContent());
        if (null === $data) {
            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        $this->initializeContaoFramework();

        /** @var Cart&Module $cart */
        $cart = IsotopeCart::findOneBy('klarna_order_id', $data->order_id);
        Isotope::setCart($cart);

        // Set the correct locale, see #7.
        $GLOBALS['TL_LANGUAGE'] = $data->locale;

        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $data->order_id);
        if (null !== $isotopeOrder) {
            if (!$isotopeOrder->isLocked()) {
                try {
                    $isotopeOrder->lock();
                } catch (\Throwable $e) {
                    return new Response('Error locking order', Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            return new JsonResponse($data);
        }

        // Create order
        $isotopeOrder = $cart->getDraftOrder();

        $isotopeOrder->klarna_order_id = $data->order_id;

        if (false === $this->checkPreCheckoutHook($isotopeOrder, (int) $cart->klarna_checkout_module) || false === $this->canCheckout($cart)) {
            return new JsonResponse([
                'error_type' => 'address_error',
                //'error_text' => $this->translator->trans('ERR.orderFailed', null, $data->locale),
                'error_text' => $GLOBALS['TL_LANG']['ERR']['orderFailed'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $isotopeOrder->lock();

        return new JsonResponse($data);
    }

    /**
     * Call the pre checkout hook.
     * As of the default logic, a `false` return value requires to cancel the order.
     */
    private function checkPreCheckoutHook(IsotopeOrder $order, int $checkoutModuleId): bool
    {
        if (isset($GLOBALS['ISO_HOOKS']['preCheckout']) && \is_array($GLOBALS['ISO_HOOKS']['preCheckout'])) {
            foreach ($GLOBALS['ISO_HOOKS']['preCheckout'] as $callback) {
                try {
                    $moduleModel = ModuleModel::findByPk($checkoutModuleId);
                    if (false === System::importStatic($callback[0])->{$callback[1]}($order, new CheckoutModule($moduleModel))) {
                        return false;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        // Don't cancel the order per default.
        return true;
    }

    /**
     * Check if the checkout can be executed.
     */
    private function canCheckout(IsotopeOrderableCollection $cart): bool
    {
        return false === $cart->isEmpty() && false === $cart->hasErrors();
    }
}
