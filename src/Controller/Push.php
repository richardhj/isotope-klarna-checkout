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
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Richardhj\IsotopeKlarnaCheckoutBundle\Api\ApiClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Push extends AbstractController
{
    private ApiClient $client;

    public function __construct(ApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Will be called post checkout, approximately 2 minutes after the user have been displayed the checkout
     * confirmation. This controller will i.a. complete the order and trigger the notifications.
     *
     * @param mixed $orderId
     */
    public function __invoke($orderId, Request $request): Response
    {
        $this->initializeContaoFramework();

        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $orderId);
        if (null === $isotopeOrder) {
            return new Response('Order not found: '.$orderId, Response::HTTP_NOT_FOUND);
        }

        $config = $isotopeOrder->getConfig();
        if (null === $config || !$config->use_klarna) {
            return new Response('Klarna is not configured in the Isotope config.', Response::HTTP_BAD_REQUEST);
        }

        $client = $this->client->httpClient($config);

        if (!$isotopeOrder->isCheckoutComplete()) {
            $client->request('POST', '/ordermanagement/v1/orders/'.$orderId.'/cancel');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $client->request('POST', '/ordermanagement/v1/orders/'.$orderId.'/acknowledge');
        $client->request('PATCH', '/ordermanagement/v1/orders/'.$orderId.'/merchant-references', ['json' => [
            'merchant_reference1' => $isotopeOrder->getDocumentNumber(),
            'merchant_reference2' => $isotopeOrder->getUniqueId(),
        ]]);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
