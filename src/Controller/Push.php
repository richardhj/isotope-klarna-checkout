<?php


namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller;


use Contao\Model;
use Contao\System;
use Isotope\Isotope;
use Isotope\Model\Config;
use Isotope\Model\ProductCollection\Order as IsotopeOrder;
use Klarna\Rest\OrderManagement\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Symfony\Component\HttpFoundation\Request;

class Push
{

    /**
     * @param integer $orderId The checkout order id.
     * @param Request $request The request.
     *
     * @return void
     *
     * @throws \RuntimeException
     * @throws \Klarna\Rest\Transport\Exception\ConnectorException
     * @throws \GuzzleHttp\Exception\RequestException
     * @throws \LogicException If Klarna is not configured in the Isotope config.
     */
    public function __invoke($orderId, Request $request)
    {
        /** @var Config|Model $config */
        $config = Isotope::getConfig();
        if (!$config->use_klarna) {
            throw new \LogicException('Klarna is not configured in the Isotope config.');
        }

        $apiUsername = $config->klarna_api_username;
        $apiPassword = $config->klarna_api_password;
        $connector   = KlarnaConnector::create($apiUsername, $apiPassword, ConnectorInterface::EU_TEST_BASE_URL);

        $klarnaOrder = new KlarnaOrder($connector, $orderId);

        $isotopeOrder = IsotopeOrder::findOneBy('klarna_order_id', $orderId);
        if (false === $this->checkPreCheckoutHook($isotopeOrder)) {
            $klarnaOrder->cancel();

            return;
        }

        $klarnaOrder->acknowledge();
        $isotopeOrder->checkout();
        $isotopeOrder->complete();

        $klarnaOrder->updateMerchantReferences(
            [
                'merchant_reference1' => $isotopeOrder->getUniqueId(),
                'merchant_reference2' => $isotopeOrder->getDocumentNumber()
            ]
        );
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
