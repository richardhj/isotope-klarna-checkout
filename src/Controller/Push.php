<?php


namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller;


use GuzzleHttp\Exception\RequestException;
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Klarna\Rest\OrderManagement\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Klarna\Rest\Transport\Exception\ConnectorException;
use Symfony\Component\HttpFoundation\Request;

class Push
{

    /**
     * Will be called post checkout, approximately 2 minutes after the user have been displayed the checkout
     * confirmation. This controller will i.a. complete the order and trigger the notifications.
     *
     * @param integer $orderId The checkout order id.
     * @param Request $request The request.
     *
     * @return void
     *
     * @throws \RuntimeException
     * @throws ConnectorException
     * @throws RequestException
     * @throws \LogicException If Klarna is not configured in the Isotope config.
     */
    public function __invoke($orderId, Request $request)
    {
        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $orderId);

        $config = $isotopeOrder->getConfig();
        if (!$config->use_klarna) {
            throw new \LogicException('Klarna is not configured in the Isotope config.');
        }

        $apiUsername = $config->klarna_api_username;
        $apiPassword = $config->klarna_api_password;
        $connector   = KlarnaConnector::create($apiUsername, $apiPassword, ConnectorInterface::EU_TEST_BASE_URL);

        $klarnaOrder = new KlarnaOrder($connector, $orderId);
        if (!$isotopeOrder->isCheckoutComplete()) {
            $klarnaOrder->cancel();

            return;
        }

        $klarnaOrder->acknowledge();
        $klarnaOrder->updateMerchantReferences(
            [
                'merchant_reference1' => $isotopeOrder->getUniqueId(),
                'merchant_reference2' => $isotopeOrder->getDocumentNumber(),
            ]
        );
    }
}
