![AtomicPay](https://github.com/atomicpay/woocommerce-plugin/blob/master/templates/images/atomicpay-plugin-header.png)
## AtomicPay For WooCommerce Plugin
This is an open source plugin for WooCommerce, allowing merchants to start accepting cryptocurrency payments on their woocommerce website by simply installing the plugin. AtomicPay is a decentralized cryptocurrency payment processor that eliminates the involvement of a third-party payment gateway, allowing merchants to accept payments directly from their customers, in a secured and trustless environment.

## Prerequisites
* Last Version Tested: Wordpress 5.0.1 WooCommerce 3.5.2

## Server Requirements

* [Wordpress](https://wordpress.org/about/requirements/) >= 4.3
* [WooCommerce](http://docs.woothemes.com/document/server-requirements/) >= 2.4
* [GMP](http://php.net/manual/en/book.gmp.php) or [BCMath](http://php.net/manual/en/book.bc.php) You may have to install GMP as most servers do not come with it, but generally BCMath is already included.
* [OpenSSL](http://us2.php.net/openssl) Must be compiled with PHP
* [PHP5 Curl](http://php.net/manual/en/curl.installation.php) Must be compiled with PHP
* PHP >= 5.4 (Tested on v7.1)

## Getting Started
AtomicPay For WooCommerce Plugin is designed to be "Plug-n-Play" installation without any programming knowledge. Anyone can do it! To set up our plugin quickly, please follow the following guide.

- You must have a AtomicPay merchant account and API keys to use this plugin. It's free to [sign-up for a AtomicPay merchant account](https://atomicpay.io/beta-registration)
- Once registered, you may retrieve the API keys by [login to merchant control panel](https://merchant.atomicpay.io/) -> API Integration page. If your key becomes compromised, you may revoke the keys by regenerating new set of keys.

## Installation
Visit the [Releases](https://github.com/atomicpay/woocommerce-plugin/releases) page of this repository and download the latest version. Once this is done, you can just go to Wordpress's Adminstration Panels > Plugins > Add New > Upload Plugin, select the downloaded file and click Install Now. After the plugin is installed, click on Activate.

**WARNING:** It is good practice to backup your databases before installing plugins. Please make sure you have created backups.

## Configuration
Configuration can be performed using the Administrator section of Wordpress.
Once logged in, you can find the configuration settings under **WooCommerce > Settings > Payments > AtomicPay**.
