=== Nexi XPay ===
Contributors: Nexi Payments
Tags: nexi, nexi payments, xpay, payment gateway, e-commerce
Author URI: https://ecommerce.nexi.it
Author: Nexi Payments SpA
Requires at least: 4.6
Tested up to: 6.7.2
WC Requires at least: 2.7.0
WC Tested up to: 9.7.1
Stable tag: 7.6.1
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

XPay is the payment gateway provided by Nexi, a leading group in Italy with the goal of shaping the future of digital payments.

== Description ==

This module allows you to connect your e-commerce website to the XPay payment gateway. 
XPay is Nexi's service that enables you to accept online and mobile payments easily and quickly.

XPay is the payment gateway provided by Nexi, a leading group in Italy with the goal of shaping the future of digital payments.

Nexi has a strong market position, managing 44 million payment cards and processing transactions totaling 186 billion euros. It has 860,000 affiliated points of sale in Italy, including over 18,000 e-commerce platforms that use the XPay payment gateway.

XPay allows you to accept online payments and is compatible with all major payment circuits. It can handle all sales channels, including e-commerce and mobile.

The XPay extension for Wordpress/WooCommerce enables you to integrate the XPay payment gateway into your website without additional implementations. It manages the customer's transition from your website to the secure Nexi environment and vice versa. The customer remains on your e-commerce site until the checkout process and is then redirected to the secure Nexi environment for payment. You don't need to handle any sensitive data.

The extension is regularly updated to incorporate the advancements and improvements of XPay.

**To use the extension, you need an XPay account, which you can easily obtain online at https://ecommerce.nexi.it/.**

== XPay Module Features ==

-	**Simple Payment**: Redirects the customer to Nexi's secure payment gateway.
-	**One-Click Payment**: Allows the end customer to store their credit card data and use it for future purchases with a single click.
-	**Recurring Payment**: Enables the merchant to tokenize the customer's card data, allowing for recurring billing and subscriptions. To enable recurring payments, you need to install the WooCommerce Subscriptions extension.
-	**Pay-By-Link**: Enables sending a payment link to the customer via email or social media.
-	**Transaction Management**: Supports international payment circuits such as Visa, Mastercard, Visa Electron, V-Pay, Maestro, American Express.
-	**Acceptance of Alternative Payment Systems**: (PayPal, Amazon Pay, Apple Pay, Google Pay, Bancomat Pay, MyBank, iDeal, Bancontact, GiroPay, etc.)
-	**Easy Checkout**: Provides a simple and intuitive payment interface (Nexi checkout page with merchant logo).
-	**Testing Area**: Allows testing the module's functionality using dummy parameters and cards, without conducting real transactions.
-	**Multilingual Support**: Recognizes the language of the merchant's portal and automatically sets it in the XPay checkout page.
-	**Accounting Management**: Allows configuring the collection mode (total or partial, immediate or deferred) directly from the module.
-	**Back-Office Operations**: Enables refund and cancellation operations directly from the CMS order management, without accessing the XPay back office.
-	**3D Secure 2**: Implemented by major international circuits (Visa, MasterCard, American Express), introduces new authentication methods to enhance and expedite the cardholder's 	purchasing experience.


*Depending on your agreement and plugin configuration, the availability of XPay features may vary. For more information, please consult the technical specifications in the subscribed service's developer portal. You can find the link to the technical specifications in the welcome email received upon service activation.*


For more information, please visit https://ecommerce.nexi.it.

== Installation ==

1. Check the compatibility of the Nexi XPay plugin with the version of Woocommerce/Wordpress installed on your site.
2. Go to your WordPress admin area and click on "Plugins » Add New."
3. Click on the "Upload Plugin" button at the top of the page.
4. Browse for the plugin .zip file on your computer.
5. Click on the "Install Now" button and then activate the plugin using the activation button.
6. For the plugin to work correctly, the bcmath PHP extension must be active on the server. If you see an error indicating the absence of this extension in the WordPress back office, please contact your site's hosting provider.

== Configuration ==

1. Go to the WooCommerce settings and click on "Payments."
2. Click on Nexi XPay to proceed with the configuration.
3. Enable the payment method.
4. Choose between the options Alias - MAC Key or API Key based on the subscribed service.
5. Enable/Disable the test mode to test the module using the test credentials provided by Nexi.
6. Click on Save.

**Adding/Removing Alternative Payment Methods**
To add or remove alternative payment methods, you need to access the XPay back office. After making any changes, save the XPay module configuration to update the payment methods.

**Bancomat Pay**
The plugin updates the order status through a notification sent by Nexi's servers. If the module doesn't receive the notification correctly, it won't be able to update the status.
By default, in case of notification issues (unreachable site, errors received from the merchant's site), the transaction is canceled, even if the payment is successfully completed. This behavior ensures that the status of Nexi transactions aligns with the order status in the CMS.
The option to cancel the transaction in case of failed notification is not available with the Bancomat Pay payment method. In case of notification anomalies, there might be discrepancies between the order status returned by the plugin and the actual transaction status on the Nexi side. Therefore, the following scenario may occur: the Bancomat Pay payment is successfully made with a positive outcome, but due to a notification problem, the plugin is unable to update the order status to "Processing."
This payment method doesn't support transaction refunds. In case of a refund, the merchant will need to proceed with a bank transfer or another method.


== Changelog ==

= 1.0.5 =
 * First Public Release.

= 1.0.6 =
 * Updated - Icon for Plugin.

= 1.0.7 =
 * Updated - Icon, Banner and Description for Plugin.
 * Tested on WordPress 4.7.2

= 1.0.8 =
* Updated - Plugin Descriptions

= 1.0.9 =
 * Updated - Plugin Descriptions

= 1.0.10 =
 * Updated - FAQ Descriptions
 * Added - Tags for Plugin

= 1.1.0 =
 * Added - New test-mode

= 1.1.3 =
 * Fixed - MAC on return

= 1.1.4 =
 * Fixed - MAC on return

= 2.0.0 =
 * Updated - Nexi Payments Refactor

= 2.0.1 =
 * Updated - Portuguese language on payment page

= 3.0.0 =
 * Updated - New configuration interface
 * Added - Choice of payment accounting (immediate / deferred)
 * Added - Support for recurring payments with WooCommerce Subscription plugin
 * Added - Gateway disabled if the payment currency is not EUR
 * Added - Refund payment via Nexi XPay API
 * Added - Payment info and XPay accounting transactions directly in WooCommerce order details

= 3.1.0 =
 * Added - OneClick: possibility for the end customer to register the credit card and be able to make subsequent payments without re-entering the data
 * Added - Ability to customize the message of payment via Nexi

= 3.1.3 =
 * Fixed - install problem
 * Fixed - payment method view problem

= 3.1.5 =
 * Fixed - view problem without WooCommerceSubscription plugin active

= 3.1.6 =
 * Fixed - incompatibility issues

= 3.2.0 =
 * Fixed - HTTP code in POST notification

= 3.3.0 =
 * Updated - Payment method also available for the exchange rate method for recurrences or in case of renewal of the overdue payment method
 * Fixed - issue with is_woocommerce_active function
 * Added - Add method description in Woocommerce payment method list
 * Fixed - duble order notification
 * Added - Add module version variable
 * Fixed - PHP notice error
 * Added - Add actions in log

= 3.3.1 =
 * Updated - list of new alternative methods available

= 3.3.2 =
 * Updated - S2S Notification retun HTTP status 500 in case of error
 * Added - Add actions in log

= 3.3.3 =
 * Fixed - view "my payment method" page in "myaccount" page

= 3.3.4 =
 * Fixed - total paid check

= 3.4.0 =
 * Added - Setting link from plugin list
 * Fixed - subscriptions payments
 * Fixed - list of payment's details in order detail page
 * Tested with WP 5.0 and WC 3.5

= 3.4.1 =
 * Fixed - configuration save

= 3.5.0 =
 * Added - Nexi image tracker
 * Updated - List of accepted payment methods downloaded automatically from the XPay merchant profile
 * Updated - Simplified configuration section
 * Fixed - PHP notice error on order's detail page

= 3.5.1 =
 * Fixed - BugFixing

= 3.6.3 =
 * Fixed - Removed constant strings to enable translations
 * Added - Improved log management

= 3.6.4 =
 * Fixed - translations in admin section

= 3.6.5 =
 * Fixed - compatibility with older versions of woocommerce

= 4.0.0 =
 * Added - 3DS 2.0 compatibility
 * Fixed - Traslation issue
 * Fixed - Minor issues
 
= 5.0.0 =
 * Changed - Management of OneClick payments: 3DSecure is required in subsequent payments.
 * Changed - Alias management in the configuration section: only one alias is required for both simple and OneClick payments.
 * Fixed - Format of the "Country" parameter sent in 3D Secure 2.0 with the ISO 3166-1 alpha-3 charset.
 * Fixed - Minor issues.
 
= 5.0.1 =
 * Fixed - Minor issues	
 
= 5.0.2 =
 * Fixed - 3D Secure 2.0 parameters format
 
= 5.1.0 =
 * Changed management of alternative payment methods available to the merchant
 * Fixed multisite issue
 * Fixed minor issues
 
= 5.2.0 =
 * Added - New payment methods Skrill, PayU, Blik, Multibanco, PoLi
 * Fixed minor issues	
 
= 6.0.0 =
 * Added - New payment method PagoDIL
 * Code refactoring
 
= 7.0.0 =
 * Added - Payment management via Api-Key
 * Code refactoring

= 7.0.1 =
 * Fixed - Minor issues

= 7.0.2 =
 * Improved logging
 * Fixed problem with recurrences

= 7.1.0 =
 * Fixed multisite issue
 * Fixed warnings and notices

= 7.1.1 =
 * Fixed - Minor issues
 
= 7.2.0 =
 * Added - OneClick feature via Api-Key
 * Added - APM (Alternative Payment Method) support via Api-Key
 * Added - Multicurrency support for Payment Cards via Api-Key
 * Added - Multilanguage feature via Api-Key, keeps the language (if supported) between the shop and the payment gateway
 
= 7.2.1 =
 * Fixed - installation issue
 
= 7.2.2 =
 * Fixed - Minor issues

= 7.2.3 =
 * Fixed - Empty checkout fieldset
 
= 7.3.0 =
 * Added - Order lock for orders via Api-Key
 * Changed - Management of bcmath PHP library
 * Fixed - Minor issues

= 7.3.1 =
 * Fixed - Subscription issue

= 7.3.2 =
 * Added - Greek language
 * Added - Multicurrency with Apple Pay and Google Pay
 * Added - Installment payments for the Greek market
 * Fixed - Minor issues
 
= 7.3.3 =
 * Fixed - Minor issues
 
= 7.3.4 =
 * Fixed - Minor issues
 
= 7.4.0 =
 * Added - New payment methods HeyLight, Klarna, PayPal Pay in 3 Installments
 * Fixed - Minor issues

= 7.4.1 =
 * Fixed - Minor issues
 
= 7.5.0 =
 * Added - New payment method: Click to Pay
 * Added - Language compatibility: German, Czech, Romanian, Slovak, Hungarian, Polish
 * Fixed - Minor issues

= 7.6.0 =
 * Added - Compatibility with WooCommerce Block Checkout
 * Added - Compatibility with WooCommerce High-Performance Order Storage (HPOS)
 
= 7.6.1 =
 * Fixed - Minor issues