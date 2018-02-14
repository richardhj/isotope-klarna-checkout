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
use Contao\Environment;
use Contao\Model;
use Contao\Module;
use Contao\PageModel;
use Contao\System;
use GuzzleHttp\Exception\RequestException;
use Isotope\Isotope;
use Isotope\Model\Config;
use Klarna\Rest\Checkout\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Klarna\Rest\Transport\Exception\ConnectorException;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetOrderLinesTrait;
use Richardhj\IsotopeKlarnaCheckoutBundle\Util\GetShippingOptionsTrait;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class KlarnaCheckout extends Module
{

    use GetOrderLinesTrait;
    use GetShippingOptionsTrait;

    /**
     * Template
     *
     * @var string
     */
    protected $strTemplate = 'mod_klarna_checkout';

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
     * @throws RequestException
     * @throws ConnectorException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \LogicException
     * @throws ServiceNotFoundException
     * @throws ServiceCircularReferenceException
     */
    protected function compile()
    {
        /** @var Config|Model $config */
        $config = Isotope::getConfig();
        if (!$config->use_klarna) {
            throw new \LogicException('Klarna is not configured in the Isotope config.');
        }

        $apiUsername = $config->klarna_api_username;
        $apiPassword = $config->klarna_api_password;
        $connector   = KlarnaConnector::create($apiUsername, $apiPassword, ConnectorInterface::EU_TEST_BASE_URL);

        $this->cart     = Isotope::getCart();
        $klarnaOrderId  = $this->cart->klarna_order_id;
        $klarnaCheckout = null;

        if ($klarnaOrderId) {
            // Resume, just make the sure the cart is up to date
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
            }
        }

        if (null === $klarnaCheckout) {
            /** @var Request $request */
            $request = System::getContainer()->get('request_stack')->getCurrentRequest();

            $klarnaCheckout = new KlarnaOrder($connector);
            $klarnaCheckout->create(
                [
                    'purchase_country'   => $config->country,
                    'purchase_currency'  => $config->currency,
                    'locale'             => $request->getLocale(),
                    'order_amount'       => $this->cart->getTotal() * 100,
                    'order_tax_amount'   => ($this->cart->getTotal() - $this->cart->getTaxFreeTotal()) * 100,
                    'order_lines'        => $this->orderLines(),
                    'merchant_urls'      => [
                        'terms'                  => $this->uri($this->klarna_terms_page),
                        'checkout'               => $this->uri($this->klarna_checkout_page),
                        'confirmation'           => $this->uri(
                            $this->klarna_confirmation_page,
                            ['klarna_order_id' => '{checkout.order.id}']
                        ),
                        'push'                   => System::getContainer()->get('router')->generate(
                            'richardhj.klarna_checkout.push',
                            ['order_id' => '{checkout.order.id}'],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                        'shipping_option_update' => System::getContainer()->get('router')->generate(
                            'richardhj.klarna_checkout.callback.shipping_option_update',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                        'address_update'         => System::getContainer()->get('router')->generate(
                            'richardhj.klarna_checkout.callback.address_update',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                        'country_change'         => System::getContainer()->get('router')->generate(
                            'richardhj.klarna_checkout.callback.country_change',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                        'order_validation'       => System::getContainer()->get('router')->generate(
                            'richardhj.klarna_checkout.callback.order_validation',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                    ],
                    'shipping_options'   => $this->shippingOptions(deserialize($this->iso_shipping_modules, true)),
                    'shipping_countries' => $config->getShippingCountries(),
                ]
            );

            $this->cart->klarna_checkout_module = $this->id;
        }

        $klarnaCheckout->fetch();
        $this->cart->klarna_order_id = $klarnaCheckout->getId();
        $this->cart->save();

        $this->Template->gui = $klarnaCheckout['html_snippet'];
    }

    /**
     * Absolute uri of given page id.
     *
     * @param int        $pageId
     * @param array|null $params
     *
     * @return string
     */
    private function uri(int $pageId, array $params = null): string
    {
        $page = PageModel::findById($pageId);
        if (null === $page) {
            return null;
        }

        if (null !== $params) {
            $params = '?'.http_build_query($params);
        }

        return Environment::get('url').'/'.$page->getFrontendUrl($params);
    }
}
