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
use Isotope\Isotope;
use Isotope\Model\Config;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\Shipping;
use Klarna\Rest\Checkout\Order as KlarnaOrder;
use Klarna\Rest\Transport\Connector as KlarnaConnector;
use Klarna\Rest\Transport\ConnectorInterface;
use Richardhj\IsotopeKlarnaCheckoutBundle\UtilEntity\OrderLine;
use Richardhj\IsotopeKlarnaCheckoutBundle\UtilEntity\ShippingOption;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class KlarnaCheckout extends Module
{

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
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \LogicException
     * @throws \Klarna\Rest\Transport\Exception\ConnectorException
     * @throws \GuzzleHttp\Exception\RequestException
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

        /** @var Cart|Model $isotopeCart */
        $isotopeCart    = Isotope::getCart();
        $klarnaOrderId  = $isotopeCart->klarna_order_id;
        $klarnaCheckout = null;

        if ($klarnaOrderId) {
            // Resume, just make the sure the cart is up to date
            $klarnaCheckout = new KlarnaOrder($connector, $klarnaOrderId);
            $klarnaCheckout->update(
                [
                    'order_amount'     => $isotopeCart->getTotal() * 100,
                    'order_tax_amount' => ($isotopeCart->getTotal() - $isotopeCart->getTaxFreeTotal()) * 100,
                    'order_lines'      => $this->orderLines(),
                ]
            );
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
                    'order_amount'       => $isotopeCart->getTotal() * 100,
                    'order_tax_amount'   => ($isotopeCart->getTotal() - $isotopeCart->getTaxFreeTotal()) * 100,
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
                    ],
                    'shipping_options'   => $this->shippingOptions(),
                    'shipping_countries' => $config->getShippingCountries(),
                ]
            );

            $isotopeCart->klarna_checkout_module = $this->id;
        }

        $klarnaCheckout->fetch();
        $isotopeCart->klarna_order_id = $klarnaCheckout->getId();
        $isotopeCart->save();

        $this->Template->gui = $klarnaCheckout['html_snippet'];
    }

    /**
     * Get the shipping options as api-conform array.
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    private function shippingOptions(): array
    {
        $return = [];

        $ids = deserialize($this->iso_shipping_modules);
        if (empty($ids) || !\is_array($ids)) {
            return [];
        }

        /** @var Shipping[] $shippingMethods */
        $shippingMethods = Shipping::findBy(['id IN ('.implode(',', $ids).')', "enabled='1'"], null);
        if (null !== $shippingMethods) {
            foreach ($shippingMethods as $shippingMethod) {
                if (!$shippingMethod->isAvailable()) {
                    continue;
                }

                $return[] = get_object_vars(ShippingOption::createForShippingMethod($shippingMethod));
            }
        }

        return $return;
    }

    /**
     * Return the items in the cart as api-conform array.
     *
     * @return array
     */
    private function orderLines(): array
    {
        $return = [];

        $cart = Isotope::getCart();
        if (null === $cart) {
            return [];
        }

        foreach ($cart->getItems() as $item) {
            $return[] = get_object_vars(OrderLine::createFromItem($item));
        }

        foreach ($cart->getSurcharges() as $surcharge) {
            if ($surcharge->addToTotal) {
                $return[] = get_object_vars(OrderLine::createForSurcharge($surcharge));
            }
        }

        return $return;
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
