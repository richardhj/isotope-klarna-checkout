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

use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Klarna\Rest\OrderManagement\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Push
{
    /**
     * Will be called post checkout, approximately 2 minutes after the user have been displayed the checkout
     * confirmation. This controller will i.a. complete the order and trigger the notifications.
     *
     * @param mixed $orderId
     */
    public function __invoke($orderId, Request $request): Response
    {
        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $orderId);
        if (null === $isotopeOrder) {
            return new Response('Order not found: '.$orderId, Response::HTTP_NOT_FOUND);
        }

        $config = $isotopeOrder->getConfig();
        if (null === $config || !$config->use_klarna) {
            return new Response('Klarna is not configured in the Isotope config.', Response::HTTP_BAD_REQUEST);
        }

        $apiUsername = $config->klarna_api_username;
        $apiPassword = $config->klarna_api_password;
        $connector = KlarnaConnector::create(
            $apiUsername,
            $apiPassword,
            $config->klarna_api_test ? ConnectorInterface::EU_TEST_BASE_URL : ConnectorInterface::EU_BASE_URL
        );

        $klarnaOrder = new KlarnaOrder($connector, $orderId);
        if (!$isotopeOrder->isCheckoutComplete()) {
            $klarnaOrder->cancel();

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $klarnaOrder->acknowledge();
        $klarnaOrder->updateMerchantReferences([
            'merchant_reference1' => $isotopeOrder->getDocumentNumber(),
            'merchant_reference2' => $isotopeOrder->getUniqueId(),
        ]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
