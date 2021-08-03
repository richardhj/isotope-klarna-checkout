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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Controller\Module;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Model;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Haste\Input\Input;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Config;
use Isotope\Model\Payment;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Isotope\Module\Checkout as NativeCheckout;
use Richardhj\IsotopeKlarnaCheckoutBundle\Api\ApiClient;
use Richardhj\IsotopeKlarnaCheckoutBundle\Dto\PaymentMethod;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @FrontendModule("iso_klarna_checkout", category="isotope", template="mod_klarna_checkout")
 */
class KlarnaCheckoutController extends AbstractFrontendModuleController
{
    private ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        /** @var Config|Model $isoConfig */
        $isoConfig = Isotope::getConfig();
        /** @var Cart|Model $isoCart */
        $isoCart = Isotope::getCart();

        $user = FrontendUser::getInstance();

        if (!$isoConfig->use_klarna) {
            $template->gui = sprintf('Klarna not configured for "%s"', $isoConfig->name);

            return $template->getResponse();
        }

        // HTTPS URIs are required for the kco callbacks
        if (false === $request->isSecure()) {
            $template->gui = 'You are not accessing this page with HTTPS.';

            return $template->getResponse();
        }

        // Process external payment, respect their output.
        $this->processExternalPaymentMethods($request, $template, $model, $isoCart);
        if (null !== $template->gui) {
            return $template->getResponse();
        }

        $client = $this->apiClient->httpClient($isoConfig);

        $klarnaOrderId = $isoCart->klarna_order_id;

        if (false === $this->canCheckout($isoCart)) {
            if ($model->iso_cart_jumpTo > 0) {
                $jumpToCart = PageModel::findPublishedById($model->iso_cart_jumpTo);
                if (null !== $jumpToCart) {
                    $jumpToCart->loadDetails();
                    throw new RedirectResponseException($jumpToCart->getFrontendUrl(null, $jumpToCart->language));
                }
            }

            $template->gui = $GLOBALS['TL_LANG']['ERR']['cartErrorInItems'];

            return $template->getResponse();
        }

        $klarnaOrder = null;
        if ($klarnaOrderId) {
            $response = $client->request('POST', '/checkout/v3/orders/'.$klarnaOrderId, [
                'json' => $this->klarnaOrderData($user, $isoConfig, $request, $model, $isoCart),
            ]);

            try {
                $klarnaOrder = $response->toArray();
            } catch (ClientException$e) {
            }
        }

        if (null === $klarnaOrder) {
            $response = $client->request('POST', '/checkout/v3/orders', [
                'json' => $this->klarnaOrderData($user, $isoConfig, $request, $model, $isoCart),
            ]);

            try {
                $klarnaOrder = $response->toArray();
            } catch (ClientException$e) {
                $template->gui = 'An error occured';

                return $template->getResponse();
            }

            $isoCart->klarna_checkout_module = $model->id;
        }

        $isoCart->klarna_order_id = $klarnaOrder['order_id'];
        $isoCart->save();

        $template->gui = $klarnaOrder['html_snippet'];

        return $template->getResponse();
    }

    private function klarnaOrderData(FrontendUser $user, Config $isoConfig, Request $request, ModuleModel $model, Cart $cart): array
    {
        // Load addresses from address book for logged in members
        $shippingAddress = null;
        $billingAddress = null;
        if (FE_USER_LOGGED_IN) {
            if ($address = Address::findDefaultBillingForMember($user->id)) {
                $shippingAddress = $this->apiClient->getApiDataFromAddress($address);
            }

            if ($address = Address::findDefaultShippingForMember($user->id)) {
                $billingAddress = $this->apiClient->getApiDataFromAddress($address);
            }
        }

        /** @var RouterInterface $router */
        $router = System::getContainer()->get('router');

        $billingFieldsConfig = $isoConfig->getBillingFieldsConfig();
        $companyConfig = array_filter($billingFieldsConfig, fn (array $c) => 'company' === $c['value'])[0] ?? [];

        return [
            'purchase_country' => $isoConfig->country,
            'purchase_currency' => $isoConfig->currency,
            'locale' => $request->getLocale(),
            'order_amount' => (int) round($cart->getTotal() * 100, 0),
            'order_tax_amount' => (int) round(($cart->getTotal() - $cart->getTaxFreeTotal()) * 100),
            'order_lines' => $this->apiClient->orderLines($cart),
            'merchant_urls' => [
                'terms' => $this->uri($model->klarna_terms_page),
                'cancellation_terms' => $this->uri($model->klarna_cancellation_terms_page),
                'checkout' => $this->uri($model->klarna_checkout_page),
                'confirmation' => sprintf('%s?klarna_order_id={checkout.order.id}', $this->uri($model->klarna_confirmation_page)),
                'push' => urldecode($router->generate('richardhj.klarna_checkout.push', ['orderId' => '{checkout.order.id}'], UrlGeneratorInterface::ABSOLUTE_URL)),
                'shipping_option_update' => urldecode($router->generate('richardhj.klarna_checkout.callback.shipping_option_update', ['orderId' => '{checkout.order.id}'], UrlGeneratorInterface::ABSOLUTE_URL)),
                'address_update' => urldecode($router->generate('richardhj.klarna_checkout.callback.address_update', ['orderId' => '{checkout.order.id}'], UrlGeneratorInterface::ABSOLUTE_URL)),
                'country_change' => urldecode($router->generate('richardhj.klarna_checkout.callback.country_change', ['orderId' => '{checkout.order.id}'], UrlGeneratorInterface::ABSOLUTE_URL)),
                'validation' => $router->generate('richardhj.klarna_checkout.callback.order_validation', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'billing_address' => $billingAddress,
            'shipping_address' => $shippingAddress,
            'shipping_options' => $this->apiClient->shippingOptions($cart, StringUtil::deserialize($model->iso_shipping_modules, true)),
            'shipping_countries' => $isoConfig->getShippingCountries() ?: null,
            'billing_countries' => $isoConfig->getBillingCountries() ?: null,
            'external_payment_methods' => $this->externalPaymentMethods($model, $request),
            'options' => [
                'allow_separate_shipping_address' => [] !== $isoConfig->getShippingFields(),
                'color_button' => $model->klarna_color_button ? '#'.$model->klarna_color_button : null,
                'color_button_text' => $model->klarna_color_button_text ? '#'.$model->klarna_color_button_text : null,
                'color_checkbox' => $model->klarna_color_checkbox ? '#'.$model->klarna_color_checkbox : null,
                'color_checkbox_checkmark' => $model->klarna_color_checkbox_checkmark ? '#'.$model->klarna_color_checkbox_checkmark : null,
                'color_header' => $model->klarna_color_header ? '#'.$model->klarna_color_header : null,
                'color_link' => $model->klarna_color_link ? '#'.$model->klarna_color_link : null,
                'allowed_customer_types' => $companyConfig && $companyConfig['mandatory'] ? ['organization'] : ($companyConfig && $companyConfig['enabled'] ? ['organization', 'person'] : ['person']),
                'require_validate_callback_success' => true,
                'show_subtotal_detail' => (bool) $model->klarna_show_subtotal_detail,
            ],
            'merchant_data' => http_build_query(['member' => $this->user->id ?? null]),
        ];
    }

    /**
     * Process external payment methods. That means, we are listening to the queries "step=complete" und "step=process".
     *
     * @throws RedirectResponseException
     * @throws PageNotFoundException
     */
    private function processExternalPaymentMethods(Request $request, Template $template, ModuleModel $model, Cart $isoCart)
    {
        switch (Input::getAutoItem('step')) {
            case 'complete':
                /** @var Order|Model $isotopeOrder */
                if (null === ($isotopeOrder = Order::findOneBy('uniqid', $request->query->get('uid')))) {
                    if ($isoCart->isEmpty()) {
                        throw new PageNotFoundException('Order with unique id not found: '.$request->query->get('uid'));
                    }

                    $template->gui = 'An error occurred.';

                    return;
                }

                // Order already completed (see isotope/core#1441)
                if ($isotopeOrder->checkout_complete) {
                    throw new RedirectResponseException($this->uri($model->klarna_confirmation_page).'?uid='.$isotopeOrder->getUniqueId());
                }

                // No external payment
                if (false === $isotopeOrder->hasPayment()) {
                    return;
                }

                $processPayment = $isotopeOrder->getPaymentMethod()->processPayment($isotopeOrder, (new Checkout($model)));
                if (true === $processPayment) {
                    // If checkout is successful, complete order and redirect to confirmation page
                    if ($isotopeOrder->checkout() && $isotopeOrder->complete()) {
                        throw new RedirectResponseException($this->uri($model->klarna_confirmation_page).'?uid='.$isotopeOrder->getUniqueId());
                    }

                    // Checkout failed, show error message
                    $template->gui = 'An error occurred.';

                    return;
                }

                // False means payment has failed
                if (false === $processPayment) {
                    $template->gui = 'An error occurred.';

                    return;
                }

                // Otherwise we assume a string that shows a message to customer
                $template->gui = $processPayment;

                return;

            case 'process':
                $isotopeOrder = $isoCart->getDraftOrder();

                $isotopeOrder->nc_notification = $model->nc_notification;
                $isotopeOrder->iso_addToAddressbook = $model->iso_addToAddressbook;

                if (false === $isotopeOrder->hasPayment()) {
                    /** @var Payment $payment */
                    $payment = Payment::findByPk($request->query->get('pay'));
                    $allowedPaymentIds = array_map('\intval', StringUtil::deserialize($model->iso_payment_modules, true));
                    if (null === $payment
                        || false === $payment->isAvailable()
                        || false === \in_array($payment->getId(), $allowedPaymentIds, true)) {
                        $template->gui = 'The payment method you\'ve selected prior is not available.';

                        return;
                    }

                    $isotopeOrder->setPaymentMethod($payment);
                }

                $isotopeOrder->save();

                // Generate checkout form that redirects to the payment provider
                $checkoutForm = $isotopeOrder->getPaymentMethod()->checkoutForm($isotopeOrder, (new Checkout($model)));
                if (false === $checkoutForm) {
                    throw new RedirectResponseException('/'.NativeCheckout::generateUrlForStep(NativeCheckout::STEP_COMPLETE, $isotopeOrder));
                }

                $template->gui = $checkoutForm;

                break;
        }
    }

    private function externalPaymentMethods(ModuleModel $model, Request $request): array
    {
        $paymentIds = StringUtil::deserialize($model->iso_payment_modules, true);
        if (empty($paymentIds)) {
            return [];
        }

        $methods = [];
        /** @var Payment[] $paymentMethods */
        $paymentMethods = Payment::findBy(['id IN ('.implode(',', $paymentIds).')', "enabled='1'"], null);
        if (null !== $paymentMethods) {
            foreach ($paymentMethods as $paymentMethod) {
                // NB: We do not check whether the payment method is available as it may differ by the billing address
                // and we must not alter the payment methods afterwards.
                // However the allowed countries get transmitted to Klarna.

                $methods[] = $paymentMethod;
            }
        }

        return array_map(
            fn (Payment $payment) => get_object_vars(new PaymentMethod($payment, sprintf(
                    '%s/%s?pay=%s', $request->getSchemeAndHttpHost(), NativeCheckout::generateUrlForStep(NativeCheckout::STEP_PROCESS), $payment->getId()
                ))),
            $methods
        );
    }

    /**
     * Absolute uri of given page id.
     *
     * @param mixed $pageId
     */
    private function uri($pageId): ?string
    {
        $page = PageModel::findById($pageId);
        if (null === $page) {
            return null;
        }

        return Environment::get('url').'/'.$page->getFrontendUrl();
    }

    private function canCheckout(Cart $cart): bool
    {
        return false === $cart->isEmpty() && false === $cart->hasErrors();
    }
}
