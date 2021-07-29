# Klarna Checkout for Isotope eCommerce

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Dependency Status][ico-dependencies]][link-dependencies]

This package provides an entire new checkout for Isotope eCommerce. It integrates the Klarna checkout iFrame into your
shop.

[More information about Klarna.][link-klarna]

## Install

Via Composer

``` bash
$ composer require richardhj/isotope-klarna-checkout
```

## Usage

The Klarna checkout will use the configuration of your Isotope shop. So make sure that you have configured your shop
configuration according to the Isotope documentation. This includes tax rates, tax classes and shipping methods.

First of all you configure Klarna for every shop config in use. Edit the Isotope shop configuration(s) and provide the
API username and API password.

This extension provides two frontend modules that both have to be implemented in your website:
1. **Klarna checkout:** Place this module on a page the user follows when s/he wants to proceed to checkout. Replaces
the native Isotope checkout module.
2. **Klarna checkout confirmation:** Place this module on a page the user get redirected after the checkout being
completed. This modules displays a confirmation iFrame (order review) and finishes the order in Isotope.

This checkout also supports [External Payment Modules][link-klarna-api-external-payment]. You can enable any payment
method you are already using (e.g., PayPal) and it should work as well! Note that for such orders the order confirmation
will differ (no Klarna iFrame).

## Who did it?

Richard Henkenjohann <richardhenkenjohann@googlemail.com>

Please consider monetary support for this extension's author in case this extension is doing business in your eCommerce.

## Demo

![checkout demo](https://raw.githubusercontent.com/richardhj/isotope-klarna-checkout/master/docs/demo-checkout.gif)

## Contributing

The API documentation is available at <https://developers.klarna.com/api>.

You always have to test/debug on a system with publicly available URIs. This means you cannot run Klarna checkout on 
localhost, as the Klarna checkout relies on the callbacks (e.g. order_validation). The checkout will fail when the
callbacks are not available.

That being said, I recommend to configure xDebug on a staging system. You also have to configure
`xdebug.remote_autostart=1`, as the callbacks get not called with a debug session cookie.


[ico-version]: https://img.shields.io/packagist/v/richardhj/isotope-klarna-checkout.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-LGPL-brightgreen.svg?style=flat-square
[ico-dependencies]: https://www.versioneye.com/php/richardhj:isotope-klarna-checkout/badge.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/richardhj/isotope-klarna-checkout
[link-dependencies]: https://www.versioneye.com/php/richardhj:isotope-klarna-checkout
[link-klarna]: https://klarna.com
[link-klarna-api-external-payment]: https://developers.klarna.com/en/de/kco-v3/checkout/external-payment-methods
