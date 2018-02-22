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
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Model;
use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use GuzzleHttp\Exception\RequestException;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Config;
use Klarna\Rest\Checkout\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Klarna\Rest\Transport\Exception\ConnectorException;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\CanCheckoutTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetShippingOptionsTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\UpdateAddressTrait;

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
     * KlarnaCheckout constructor.
     *
     * @param ModuleModel $module
     * @param string      $column
     */
    public function __construct($module, $column = 'main')
    {
        parent::__construct($module, $column);

        $this->config = Isotope::getConfig();
        $this->cart   = Isotope::getCart();
        $this->user   = FrontendUser::getInstance();
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
     * @throws ConnectorException        When the API replies with an error response
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

        if (!Environment::get('ssl')) {
            // HTTPS uris are required for the kco callbacks
            $this->Template->gui = 'You are not accessing this page with HTTPS.';

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
                        'order_amount'     => $this->cart->getTotal() * 100,
                        'order_tax_amount' => ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100,
                        'order_lines'      => $this->orderLines(),
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
                /** @var PageModel $objPage */
                global $objPage;

                // Load addresses from address book for logged in members
                $shippingAddress = null;
                $billingAddress  = null;
                if (FE_USER_LOGGED_IN) {
                    $address         = Address::findDefaultBillingForMember($this->user->id);
                    $shippingAddress = $this->getApiDataFromAddress($address);

                    $address        = Address::findDefaultShippingForMember($this->user->id);
                    $billingAddress = $this->getApiDataFromAddress($address);
                }

                $klarnaCheckout = new KlarnaOrder($connector);
                $klarnaCheckout->create(
                    [
                        'purchase_country'   => $this->config->country,
                        'purchase_currency'  => $this->config->currency,
                        'locale'             => $objPage->language,
                        'order_amount'       => $this->cart->getTotal() * 100,
                        'order_tax_amount'   => ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100,
                        'order_lines'        => $this->orderLines(),
                        'merchant_urls'      => [
                            'terms'                  => $this->uri($this->klarna_terms_page),
                            'checkout'               => $this->uri($this->klarna_checkout_page),
                            'confirmation'           => $this->uri($this->klarna_confirmation_page)
                                                        .'?klarna_order_id={checkout.order.id}',
                            'push'                   => Environment::get('url')
                                                        .'/system/modules/isotope-klarna-checkout/public/push.php'
                                                        .'?klarna_order_id={checkout.order.id}',
                            'shipping_option_update' => Environment::get('url')
                                                        .'/system/modules/isotope-klarna-checkout/public/shipping_option_update.php'
                                                        .'?klarna_order_id={checkout.order.id}',
                            'address_update'         => Environment::get('url')
                                                        .'/system/modules/isotope-klarna-checkout/public/address_update.php'
                                                        .'?klarna_order_id={checkout.order.id}',
                            'country_change'         => Environment::get('url')
                                                        .'/system/modules/isotope-klarna-checkout/public/country_change.php'
                                                        .'?klarna_order_id={checkout.order.id}',
                            'validation'             => Environment::get('url')
                                                        .'/system/modules/isotope-klarna-checkout/public/validation.php',
                        ],
                        'billing_address'    => $billingAddress,
                        'shipping_address'   => $shippingAddress,
                        'shipping_options'   => $this->shippingOptions(deserialize($this->iso_shipping_modules, true)),
                        'shipping_countries' => $this->config->getShippingCountries(),
                        'options'            => [
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
                        'merchant_data'      => http_build_query(['member' => $this->user->id ?? null]),
                    ]
                );

                $this->cart->klarna_checkout_module = $this->id;
            }

            $klarnaCheckout->fetch();

        } catch (RequestException $e) {
            $this->Template->gui = $e->getResponse()->getReasonPhrase();
            System::log('KCO error: '.strip_tags($e->getMessage()), __METHOD__, TL_ERROR);

            return;
        }

        $this->cart->klarna_order_id = $klarnaCheckout->getId();
        $this->cart->save();

        $this->Template->gui = $klarnaCheckout['html_snippet'];
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
}
