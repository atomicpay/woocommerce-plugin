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

#### STEP 1
Login to your [AtomicPay Merchant Account](https://merchant.atomicpay.io/login) and go to [API Integration](https://merchant.atomicpay.io/apiIntegration) page. You will need the following vvalues for next step: ACCOUNT ID, PRIVATE KEY and PUBLIC KEY.

![API Keys](https://github.com/atomicpay/woocommerce-plugin/blob/master/templates/images/getting-keys.png)

#### STEP 2
Here you will need to copy and paste the values from STEP 1 into the corresponding fields: Account ID, Private Key and Public Key.

![Step 1](https://github.com/atomicpay/woocommerce-plugin/blob/master/templates/images/step-1.png)

#### STEP 3
Click on the button Request Authorization. The plugin will attempt to connect to AtomicPay Server for an authorization.

![Step 2](https://github.com/atomicpay/woocommerce-plugin/blob/master/templates/images/step-2.png)

#### STEP 4
Once authorization is successful, you should see the following dialog popup.

![Step 3](https://github.com/atomicpay/woocommerce-plugin/blob/master/templates/images/step-3.png)

#### FINAL STEP
Next, we will need you to select a default Transaction Speed value. HIGH Risk speed require 1 confirmation, and can be used for digital goods or low-risk items. MEDIUM Risk speed require at least 2 confirmations, and should be used for mid-value items. LOW Risk speed require at least 6 confirmations (averaging 30 mins, depending on selected cryptocurrency), and should be used for high-value items.

![Step 4](https://github.com/atomicpay/woocommerce-plugin/blob/master/templates/images/step-4.png)
