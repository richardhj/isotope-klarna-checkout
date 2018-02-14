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
use Contao\System;
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderValidation
{

    /**
     * Will be called before completing the purchase to validate the information provided by the consumer in Klarna's
     * Checkout iframe.
     * The response will redirect to an error page if the preCheckout hook will fail.
     *
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

        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $data->order_id);

        $response = new JsonResponse([]);
        if (false === $this->checkPreCheckoutHook($isotopeOrder)) {
            $response = new JsonResponse(
                [
                    'error_type' => 'address_error',
//                    'error_text' => $this->translator->trans('ERR.orderFailed', null, $data->locale),
                    'error_text' => $GLOBALS['TL_LANG']['ERR']['orderFailed'],
                ]
            );
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }

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
