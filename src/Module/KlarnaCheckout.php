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

namespace Richardhj\IsotopeKlarnaCheckoutBundle\Module;


use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\Exception\NoRootPageFoundException;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Model;
use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use GuzzleHttp\Exception\RequestException;
use Haste\Input\Input;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Config;
use Isotope\Model\Payment;
use Isotope\Model\ProductCollection\Order;
use Klarna\Rest\Checkout\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Klarna\Rest\Transport\Exception\ConnectorException;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\CanCheckoutTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetShippingOptionsTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\PaymentMethod;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\UpdateAddressTrait;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class KlarnaCheckout extends Module
{

    use CanCheckoutTrait;
    use GetOrderLinesTrait;
    use GetShippingOptionsTrait;
    use UpdateAddressTrait;

    /**
     * Template
     *
     * @var string
     */
    protected $strTemplate = 'mod_klarna_checkout';

    /**
     * @var Config|Model
     */
    private $config;

    /**
     * @var FrontendUser
     */
    private $user;

    /**
     * @var Request
     */
    private $request;

    /**
     * KlarnaCheckout constructor.
     *
     * @param ModuleModel $module
     * @param string      $column
     *
     * @throws ServiceNotFoundException
     * @throws ServiceCircularReferenceException
     */
    public function __construct($module, $column = 'main')
    {
        parent::__construct($module, $column);

        $this->config  = Isotope::getConfig();
        $this->cart    = Isotope::getCart();
        $this->user    = FrontendUser::getInstance();
        $this->request = System::getContainer()->get('request_stack')->getCurrentRequest();
    }

    /**
     * Parse the template
     *
     * @return string
     */
    public function generate(): string
    {
        if ('BE' === TL_MODE) {
            $template = new BackendTemplate('be_wildcard');
            $template->setData(
                [
                    'wildcard' => '### '.strtoupper($GLOBALS['TL_LANG']['FMD'][$this->type][0]).' ###',
                    'title'    => $this->headline,
                    'id'       => $this->id,
                    'link'     => $this->name,
                    'href'     => 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id,
                ]
            );

            return $template->parse();
        }

        return parent::generate();
    }

    /**
     * Compile the current element
     *
     * @throws RedirectResponseException
     * @throws PageNotFoundException
     * @throws NoRootPageFoundException  If no root page is found when trying to load the page details of the jumpToCart
     * @throws \InvalidArgumentException If the JSON cannot be parsed
     * @throws \RuntimeException         On an unexpected API response
     * @throws \RuntimeException         If the response content type is not JSON
     * @throws \LogicException           When Guzzle cannot populate the response
     */
    protected function compile()
    {
        if (!$this->config->use_klarna) {
            $this->Template->gui = sprintf('Klarna not configured for "%s"', $this->config->name);

            return;
        }

        if (false === $this->request->isSecure()) {
            // HTTPS uris are required for the kco callbacks
            $this->Template->gui = 'You are not accessing this page with HTTPS.';

            return;
        }

        // Process external payment, respect their output.
        $this->processExternalPaymentMethods();
        if (null !== $this->Template->gui) {
            return;
        }

        $apiUsername = $this->config->klarna_api_username;
        $apiPassword = $this->config->klarna_api_password;
        $connector   = KlarnaConnector::create(
            $apiUsername,
            $apiPassword,
            $this->config->klarna_api_test ? ConnectorInterface::EU_TEST_BASE_URL : ConnectorInterface::EU_BASE_URL
        );

        $klarnaOrderId  = $this->cart->klarna_order_id;
        $klarnaCheckout = null;

        if (false === $this->canCheckout()) {
            if ($this->iso_cart_jumpTo > 0) {
                $jumpToCart = PageModel::findPublishedById($this->iso_cart_jumpTo);
                if (null !== $jumpToCart) {
                    $jumpToCart->loadDetails();
                    Controller::redirect($jumpToCart->getFrontendUrl(null, $jumpToCart->language));
                }
            }

            $this->Template->gui = $GLOBALS['TL_LANG']['ERR']['cartErrorInItems'];

            return;
        }

        if ($klarnaOrderId) {
            // Resume, just make sure the cart is up to date
            $klarnaCheckout = new KlarnaOrder($connector, $klarnaOrderId);
            try {
                $klarnaCheckout->update(
                    [
                        'order_amount'             => round($this->cart->getTotal() * 100),
                        'order_tax_amount'         => round(
                            ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100
                        ),
                        'order_lines'              => $this->orderLines(),
                        'external_payment_methods' => $this->externalPaymentMethods(),
                    ]
                );
            } catch (RequestException $e) {
                $klarnaCheckout = null;
            } catch (ConnectorException $e) {
                $klarnaCheckout = null;
            }
        }

        try {
            if (null === $klarnaCheckout) {
                // Load addresses from address book for logged in members
                $shippingAddress = null;
                $billingAddress  = null;
                if (FE_USER_LOGGED_IN) {
                    if ($address = Address::findDefaultBillingForMember($this->user->id)) {
                        $shippingAddress = $this->getApiDataFromAddress($address);
                    }

                    if ($address = Address::findDefaultShippingForMember($this->user->id)) {
                        $billingAddress = $this->getApiDataFromAddress($address);
                    }
                }

                $klarnaCheckout = new KlarnaOrder($connector);
                $klarnaCheckout->create(
                    [
                        'purchase_country'         => $this->config->country,
                        'purchase_currency'        => $this->config->currency,
                        'locale'                   => $this->request->getLocale(),
                        'order_amount'             => round($this->cart->getTotal() * 100),
                        'order_tax_amount'         => round(
                            ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100
                        ),
                        'order_lines'              => $this->orderLines(),
                        'merchant_urls'            => [
                            'terms'                  => $this->uri($this->klarna_terms_page),
                            'checkout'               => $this->uri($this->klarna_checkout_page),
                            'confirmation'           => $this->uri($this->klarna_confirmation_page)
                                                        .'?klarna_order_id={checkout.order.id}',
                            'push'                   => urldecode(
                                System::getContainer()->get('router')->generate(
                                    'richardhj.klarna_checkout.push',
                                    ['orderId' => '{checkout.order.id}'],
                                    UrlGeneratorInterface::ABSOLUTE_URL
                                )
                            ),
                            'shipping_option_update' => urldecode(
                                System::getContainer()->get('router')->generate(
                                    'richardhj.klarna_checkout.callback.shipping_option_update',
                                    ['orderId' => '{checkout.order.id}'],
                                    UrlGeneratorInterface::ABSOLUTE_URL
                                )
                            ),
                            'address_update'         => urldecode(
                                System::getContainer()->get('router')->generate(
                                    'richardhj.klarna_checkout.callback.address_update',
                                    ['orderId' => '{checkout.order.id}'],
                                    UrlGeneratorInterface::ABSOLUTE_URL
                                )
                            ),
                            'country_change'         => urldecode(
                                System::getContainer()->get('router')->generate(
                                    'richardhj.klarna_checkout.callback.country_change',
                                    ['orderId' => '{checkout.order.id}'],
                                    UrlGeneratorInterface::ABSOLUTE_URL
                                )
                            ),
                            'validation'             => System::getContainer()->get('router')->generate(
                                'richardhj.klarna_checkout.callback.order_validation',
                                [],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ),
                        ],
                        'billing_address'          => $billingAddress,
                        'shipping_address'         => $shippingAddress,
                        'shipping_options'         => $this->shippingOptions(
                            deserialize($this->iso_shipping_modules, true)
                        ),
                        'shipping_countries'       => $this->config->getShippingCountries(),
                        'external_payment_methods' => $this->externalPaymentMethods(),
                        'options'                  => [
                            'allow_separate_shipping_address'   => [] !== $this->config->getShippingFields(),
                            'color_button'                      => $this->klarna_color_button
                                ? '#'.$this->klarna_color_button
                                : null,
                            'color_button_text'                 => $this->klarna_color_button_text
                                ? '#'.$this->klarna_color_button_text
                                : null,
                            'color_checkbox'                    => $this->klarna_color_checkbox
                                ? '#'.$this->klarna_color_checkbox
                                : null,
                            'color_checkbox_checkmark'          => $this->klarna_color_checkbox_checkmark
                                ? '#'.$this->klarna_color_checkbox_checkmark
                                : null,
                            'color_header'                      => $this->klarna_color_header
                                ? '#'.$this->klarna_color_header
                                : null,
                            'color_link'                        => $this->klarna_color_link
                                ? '#'.$this->klarna_color_link
                                : null,
                            'require_validate_callback_success' => true,
                            'show_subtotal_detail'              => (bool)$this->klarna_show_subtotal_detail,
                        ],
                        'merchant_data'            => http_build_query(['member' => $this->user->id ?? null]),
                    ]
                );

                $this->cart->klarna_checkout_module = $this->id;
            }

            $klarnaCheckout->fetch();

        } catch (RequestException $e) {
            $this->handleApiException($e);

            return;
        } catch (ConnectorException $e) {
            $this->handleApiException($e);

            return;
        }

        $this->cart->klarna_order_id = $klarnaCheckout->getId();
        $this->cart->save();

        $this->Template->gui = $klarnaCheckout['html_snippet'];
    }

    /**
     * Process external payment methods. That means, we are listening to the queries "step=complete" und "step=process".
     *
     * @throws RedirectResponseException
     * @throws PageNotFoundException
     */
    private function processExternalPaymentMethods()
    {
        switch (Input::getAutoItem('step')) {
            case 'complete':
                /** @var Order|Model $isotopeOrder */
                if (null === ($isotopeOrder = Order::findOneBy('uniqid', $this->request->query->get('uri')))) {
                    if ($this->cart->isEmpty()) {
                        throw new PageNotFoundException(
                            'Order with unique id not found: '.$this->request->query->get('uri')
                        );
                    }

                    $this->Template->gui = 'An error occurred.';

                    return;
                }

                // Order already completed (see isotope/core#1441)
                if ($isotopeOrder->checkout_complete) {
                    throw new RedirectResponseException(
                        $this->uri($this->klarna_confirmation_page).'?klarna_order_id='.$isotopeOrder->klarna_order_id
                    );
                }

                // No external payment
                if (false === $isotopeOrder->hasPayment()) {
                    return;
                }

                $processPayment = $isotopeOrder->getPaymentMethod()->processPayment($isotopeOrder, $this);
                if (true === $processPayment && $isotopeOrder->checkout() && $isotopeOrder->complete()) {
                    throw new RedirectResponseException(
                        $this->uri($this->klarna_confirmation_page).'?klarna_order_id='
                        .$isotopeOrder->klarna_order_id
                    );
                }

                $this->Template->gui = 'An error occurred.';

                return;

                break;


            case 'process':
                $isotopeOrder = $this->cart->getDraftOrder();

                if (false === $isotopeOrder->hasPayment()) {
                    /** @var Payment $payment */
                    $payment = Payment::findByPk($this->request->query->get('pay'));
                    if (null === $payment || false === $payment->isAvailable()) {
                        $this->Template->gui = 'An error occurred.';

                        return;
                    }

                    $isotopeOrder->setPaymentMethod($payment);
                    $isotopeOrder->save();
                }

                // Generate checkout form that redirects to the payment provider
                $checkoutForm = $isotopeOrder->getPaymentMethod()->checkoutForm($isotopeOrder, $this);
                if (false === $checkoutForm) {
                    throw new RedirectResponseException($this->request->getUri().'?step=complete');
                }

                $this->Template->gui = $checkoutForm;

                break;
        }
    }

    /**
     * @return array
     */
    private function externalPaymentMethods(): array
    {
        $paymentIds = deserialize($this->iso_payment_modules, true);
        if (empty($paymentIds)) {
            return [];
        }

        $methods = [];
        /** @var Payment[] $paymentMethods */
        $paymentMethods = Payment::findBy(['id IN ('.implode(',', $paymentIds).')', "enabled='1'"], null);
        if (null !== $paymentMethods) {
            foreach ($paymentMethods as $paymentMethod) {
                if (!$paymentMethod->isAvailable()) {
                    continue;
                }

                $methods[] = $paymentMethod;
            }
        }

        return array_map(
            function (Payment $payment) {
                return get_object_vars(
                    PaymentMethod::createForPaymentMethod(
                        $payment,
                        $this->request->getUri().'?step=process&pay='.$payment->getId()
                    )
                );
            },
            $methods
        );
    }

    /**
     * Absolute uri of given page id.
     *
     * @param int $pageId
     *
     * @return string
     */
    private function uri(int $pageId): string
    {
        $page = PageModel::findById($pageId);
        if (null === $page) {
            return null;
        }

        return Environment::get('url').'/'.$page->getFrontendUrl();
    }

    /**
     * Process an API exception.
     *
     * @param RequestException|ConnectorException $e
     */
    private function handleApiException($e)
    {
        $response = $e->getResponse();

        $this->Template->gui = $GLOBALS['TL_LANG']['XPT']['error'].'<br>';
        $this->Template->gui .= 'Current time: '.date('Y-m-d H:i:s').'<br>';
        if ($response !== null) {
            $this->Template->gui .= 'Error code: '.$response->getReasonPhrase();
        }

        System::log('KCO error: '.strip_tags($e->getMessage()), __METHOD__, TL_ERROR);
    }
}
