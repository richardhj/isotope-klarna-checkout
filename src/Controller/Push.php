<?php


namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller;


use Isotope\Model\ProductCollection\Order;
use Klarna\Rest\OrderManagement\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Symfony\Component\HttpFoundation\Request;

class Push
{

    /**
     * @param int     $orderId The checkout order id.
     * @param Request $request The request.
     *
     * @return void
     */
    public function __invoke($orderId, Request $request)
    {
        $merchantId   = '0';
        $sharedSecret = 'sharedSecret';
        $connector    = KlarnaConnector::create($merchantId, $sharedSecret, ConnectorInterface::EU_TEST_BASE_URL);
        $order        = new KlarnaOrder($connector, $orderId);
        $order->acknowledge();

        $isotopeOrder = Order::findOneBy('klarna_order_id', $orderId);
        $order->updateMerchantReferences(
            [
                'merchant_reference1' => $isotopeOrder->getUniqueId(),
            ]
        );

        $isotopeOrder->checkout();
        $isotopeOrder->complete();
    }
}
