<?php
/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     6.0.0
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */

namespace Nexi;

class WC_Gateway_Admin extends \WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id = 'xpay';

        $this->method_title = __('Nexi XPay', 'woocommerce-gateway-nexi-xpay');
        $this->method_description = __('Payment plugin for payment cards and alternative methods. Powered by Nexi.', 'woocommerce-gateway-nexi-xpay');
        $this->title = $this->method_title;
        $this->description = __("Pay securely by credit, debit and prepaid card. Powered by Nexi.", 'woocommerce-gateway-nexi-xpay');

        if (\WC_Admin_Settings::get_option('xpay_logo_small') == "") {
            $this->icon = WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL;
        } else {
            $this->icon = \WC_Admin_Settings::get_option('xpay_logo_small');
        }

        $this->init_form_fields();
        $this->init_settings();
        $this->set_nexi_default_gateway();

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));
    }

    private function set_nexi_default_gateway()
    {
        if (array_key_exists('nexi_gateway', $this->settings) && $this->settings['nexi_gateway'] != null) {
            return;
        }

        $this->settings['nexi_gateway'] = GATEWAY_XPAY;

        if (
            array_key_exists('nexi_npg_api_key', $this->settings) &&
            $this->settings['nexi_npg_api_key'] != null &&
            array_key_exists('xpay_npg_available_methods', $this->settings)
        ) {
            $this->settings['nexi_gateway'] = GATEWAY_NPG;
        }

        update_option(WC_SETTINGS_KEY, $this->settings);
    }

    public static function my_error_notice_xpay()
    {
        ?>
        <div class="error notice">
            <p><?php echo __('Invalid credentials. Check and try again.', 'woocommerce-gateway-nexi-xpay'); ?></p>
        </div>
        <?php
    }

    public static function my_error_notice_npg()
    {
        ?>
        <div class="error notice">
            <p><?php echo __('Invalid API Key. Check and try again.', 'woocommerce-gateway-nexi-xpay'); ?></p>
        </div>
        <?php
    }

    private function save_apple_files()
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!empty($_FILES['woocommerce_xpay_applepay_merchant_identifier_certificate']['tmp_name'])) {
            $uploaded = wp_handle_upload($_FILES['woocommerce_xpay_applepay_merchant_identifier_certificate'], ['test_form' => false, 'test_type' => false]);

            if (!isset($uploaded['error'])) {
                update_option('nexi_applepay_merchant_identifier_certificate', $uploaded['file']);
            }
        }

        if (!empty($_FILES['woocommerce_xpay_applepay_merchant_identifier_certificate_key']['tmp_name'])) {
            $uploaded = wp_handle_upload($_FILES['woocommerce_xpay_applepay_merchant_identifier_certificate_key'], ['test_form' => false, 'test_type' => false]);

            if (!isset($uploaded['error'])) {
                update_option('nexi_applepay_merchant_identifier_certificate_key', $uploaded['file']);
            }
        }

        if (!empty($_FILES['woocommerce_xpay_applepay_ca_root_certificate']['tmp_name'])) {
            $uploaded = wp_handle_upload($_FILES['woocommerce_xpay_applepay_ca_root_certificate'], ['test_form' => false, 'test_type' => false]);

            if (!isset($uploaded['error'])) {
                update_option('nexi_applepay_ca_root_certificate', $uploaded['file']);
            }
        }
    }

    function process_admin_save()
    {
        $this->process_admin_options();

        $this->update_profile_info();

        $this->save_apple_files();
    }

    private function update_profile_info()
    {
        if (\Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG($this->settings)) {
            try {
                $npg_ok = !is_null(WC_Gateway_NPG_API::getInstance()->get_profile_info());
            } catch (\Exception $exc) {
                $npg_ok = false;

                add_action('admin_notices', WC_Gateway_Admin::class . "::my_error_notice_npg");

                Log::actionWarning($exc->getMessage());
            }

            if (!$npg_ok) {
                delete_option('xpay_npg_available_methods');
            } else {
                delete_option('xpay_available_methods');
            }
        } else {
            try {
                $xpay_ok = !is_null(WC_Gateway_XPay_API::getInstance()->get_profile_info());
            } catch (\Exception $exc) {
                $xpay_ok = false;

                add_action('admin_notices', WC_Gateway_Admin::class . "::my_error_notice_xpay");

                Log::actionWarning($exc->getMessage());
            }

            if (!$xpay_ok) {
                delete_option('xpay_available_methods');
                delete_option('xpay_logo_small');
                delete_option('xpay_logo_large');
            } else {
                delete_option('xpay_npg_available_methods');
            }
        }

        if (!extension_loaded('bcmath') || !function_exists("bcmul") || !function_exists("bcdiv")) {
            Log::actionWarning("Library bcmath not loaded or function bcdiv|bcmul not defined!");
        }
    }

    function init_form_fields()
    {
        parent::init_form_fields();

        $descriptionEnable = __('For a correct behavior of the module, check in the configuration section of the Nexi back-office that the transaction cancellation in the event of a failed notification is set.', 'woocommerce-gateway-nexi-xpay') . '<br/><br/>'
            . __('A POST notification by the Nexi servers is sent to the following address, containing information on the outcome of the payment.', 'woocommerce-gateway-nexi-xpay') . '<br/>'
            . '<b>' . get_rest_url(null, "woocommerce-gateway-nexi-xpay/s2s/") . "(xpay|npg)/(order id)" . '</b><br/>'
            . __('The notification is essential for the functioning of the plugin, it is therefore necessary that it is not blocked or filtered by the site infrastructure.', 'woocommerce-gateway-nexi-xpay');

        $description3ds20 = "";
        $description3ds20 .= __('The 3D Secure 2 protocol adopted by the main international circuits (Visa, MasterCard, American Express), introduces new authentication methods, able to improve and speed up the cardholder\'s purchase experience.', 'woocommerce-gateway-nexi-xpay');
        $description3ds20 .= '<br><br>';
        $description3ds20 .= __('By activating this option it is established that the terms and conditions that you submit to your customers, with particular reference to the privacy policy, are foreseen to include the acquisition and processing of additional data provided by the', 'woocommerce-gateway-nexi-xpay');
        $description3ds20 .= ' <a class="xpay-only-text" href=\"https://ecommerce.nexi.it/specifiche-tecniche/3dsecure2/introduzione.html\" target="_blank">' . __('3D Secure 2 Service', 'woocommerce-gateway-nexi-xpay') . '</a> ';
        $description3ds20 .= ' <span class="npg-only-text">' . __('3D Secure 2 Service', 'woocommerce-gateway-nexi-xpay') . '</span> ';
        $description3ds20 .= __('(for example, shipping and / or invoicing address, payment details). Nexi and the International Circuits use the additional data collected separately for the purpose of fraud prevention.', 'woocommerce-gateway-nexi-xpay');

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __("Enable Nexi XPay payment plugin.", 'woocommerce-gateway-nexi-xpay'),
                'description' => $descriptionEnable,
                'default' => 'no',
            ),
        );

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_gateway' => array(
                'title' => __('Choose the type of credentials you have available for XPay', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => array(
                    GATEWAY_XPAY => __('Alias and MAC Key', 'woocommerce-gateway-nexi-xpay'),
                    GATEWAY_NPG => __('APIKey', 'woocommerce-gateway-nexi-xpay')
                ),
                'description' => '- ' . __('Select "Alias and MAC Key" option if you received the credentials of the production environment in the Welcome Mail received from Nexi during the activation of the service', 'woocommerce-gateway-nexi-xpay') . '<br />'
                    . '- ' . __('Select "APIKey" option if you use the API Key as the credential of the production environment generated from the Back Office XPay. Follow the directions in the developer portal for the correct generation process.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'gateway-input',
            )
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'integration_type' => array(
                'title' => __('Type of integration', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => array(
                    'redirect' => __('Hosted Payment Page with redirect', 'woocommerce-gateway-nexi-xpay'),
                    'build' => __('Build with embedded checkout', 'woocommerce-gateway-nexi-xpay')
                ),
                'description' => '- ' . __('Select "Hosted Payment Page with redirect" if you want to use "Hosted Payment Page" integration type where the customer is redirected to XPay external checkout page.', 'woocommerce-gateway-nexi-xpay') . '<br />'
                    . '- ' . __('Select "Build with embedded checkout" if you want to use "XPay Build" integration type where the payment form is on checkout.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'integration-type',
            )
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_alias' => array(
                'title' => __('Alias', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'nexi_xpay_mac' => array(
                'title' => __('Key MAC', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_npg_api_key' => array(
                'title' => __('API Key', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('Generated from the Back Office XPay. Follow the directions in the developer portal for the correct generation process.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only'
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_test_mode' => array(
                'title' => __('Enable/Disable TEST Mode', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable Nexi XPay plugin in testing mode.', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => '<span class="xpay-only-text" >' . __('Please register at', 'woocommerce-gateway-nexi-xpay') . ' <a href="https://ecommerce.nexi.it/area-test" target="_blank">ecommerce.nexi.it/area-test</a> ' . __('to get the test credentials.', 'woocommerce-gateway-nexi-xpay') . '</span><span class="npg-only-text">' . __('Please refer to Dev Portal to get access to the Sandbox', 'woocommerce-gateway-nexi-xpay') . '</span>',
                'class' => 'test-mode-input',
            ),
            'nexi_xpay_accounting' => array(
                'title' => __('Capture method', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => array(
                    "C" => __('Immediate', 'woocommerce-gateway-nexi-xpay'),
                    "D" => __('Deferred', 'woocommerce-gateway-nexi-xpay')
                ),
                'description' => __('This field identifies the collection method the merchant wishes to apply to the individual transaction. If set to:<br />-C (immediate), the transaction, if authorized, is also collected without further intervention by the operator and regardless of the default profile set on the terminal.<br />-D (deferred), meaning the field is left blank, the transaction, if authorized, is handled according to the terminal profile.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'nexi_xpay_3ds20_enabled' => array(
                'title' => __('Enable 3D Secure 2 service', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable the sending of the fields for the 3D Secure 2 service', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => $description3ds20,
            ),
            'nexi_xpay_oneclick_enabled' => array(
                'title' => __('Enable/Disable OneClick', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable Nexi XPay for OneClick payment', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('Enable Nexi XPay for OneClick payment. Make sure that this option is also enabled on your terminal configuration.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'oneclick-enabled-config',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_multicurrency_enabled' => array(
                'title' => __('Enable/Disable Multicurrency', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable Nexi XPay for Multicurrency payments', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('Enable this option to make the payment methods available for different currencies. If your shop only supports a currency other than the euro, please enable the feature. To have the complete list of the supported currencies, please visit the developer Portal. Make sure that this option is also enabled on your terminal configuration.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_installments_enabled' => array(
                'title' => __('Enable/Disable Installment Payments', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable/Disable Installment Payments', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('Enable this option to use installment payments via XPay. This functionality is only available to merchants with Greek VAT Number. Before enabling this functionality, make sure it is available on your terminal with your payment provider.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-redirect-only installments-enabled',
            ),
        ));

        $maxInstallmentsOptions = array();

        for ($i = 2; $i < 100; $i++) {
            $maxInstallmentsOptions[$i] = $i;
        }

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_max_installments' => array(
                'title' => __('Maximum number of installments regardless of the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => $maxInstallmentsOptions,
                'label' => __('Maximum number of installments regardless of the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('1 to 99 installments, 1 for one shot payment. Before set up a configuration, make sure to check with your payment provider what is the maximum number accepted for your terminal.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only installments-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_installments_ranges' => array(
                'title' => __('Maximum number of installments depending on the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'field_group',
                'label' => __('Maximum number of installments depending on the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'default' => '[]',
                'description' => __('Add amount and installments for each row. The installments limit is 99', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only installments-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_max_installments' => array(
                'title' => __('Maximum number of installments regardless of the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => $maxInstallmentsOptions,
                'label' => __('Maximum number of installments regardless of the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('1 to 99 installments, 1 for one shot payment. Before set up a configuration, make sure to check with your payment provider what is the maximum number accepted for your terminal.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only installments-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_installments_ranges' => array(
                'title' => __('Maximum number of installments depending on the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'field_group',
                'label' => __('Maximum number of installments depending on the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'default' => '[]',
                'description' => __('Add amount and installments for each row. The installments limit is 99', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only installments-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'gpay_title' => array(
                'title' => __('Google Pay configuration', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Google Pay configuration', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'title',
                'class' => '',
            ),
            'gpay_label' => array(
                'title' => __('Google Pay instructions', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'gpay_label',
                'class' => 'gpay-label',
            ),
            'gpay_integration_type' => array(
                'title' => __('Redirect or button', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Redirect or button', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => [
                    'redirect' => __('Redirect', 'woocommerce-gateway-nexi-xpay'),
                    'button' => __('Button', 'woocommerce-gateway-nexi-xpay'),
                ],
                'default' => 'redirect',
                'desc_tip' => true,
                'class' => 'gpay-integration-type',
            ),
            'gpay_merchant_name' => array(
                'title' => __('Google merchant name', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Google merchant name', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'default' => '',
                'desc_tip' => true,
                'class' => 'gpay-button-only',
            ),
            'gpay_merchant_id' => array(
                'title' => __('Google merchant Id', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Google merchant Id', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'default' => '',
                'desc_tip' => true,
                'class' => 'gpay-button-only',
            ),
            'gpay_gateway_merchant_id' => array(
                'title' => __('Google gateway merchant Id', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Google gateway merchant Id', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'default' => '',
                'desc_tip' => true,
                'class' => 'gpay-button-only',
            ),
            'gpay_button_type' => array(
                'title' => __('Button type', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Button type', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => [
                    "buy" => __("Buy", "woocommerce-gateway-nexi-xpay"),
                    "book" => __("Book", "woocommerce-gateway-nexi-xpay"),
                    "checkout" => __("Checkout", "woocommerce-gateway-nexi-xpay"),
                    "donate" => __("Donate", "woocommerce-gateway-nexi-xpay"),
                    "order" => __("Order", "woocommerce-gateway-nexi-xpay"),
                    "pay" => __("Pay", "woocommerce-gateway-nexi-xpay"),
                    "plain" => __("Plain", "woocommerce-gateway-nexi-xpay"),
                    "subscribe" => __("Subscribe", "woocommerce-gateway-nexi-xpay"),
                ],
                'default' => 'buy',
                'desc_tip' => true,
                'class' => 'gpay-button-only',
            ),
            'gpay_button_color' => array(
                'title' => __('Button color', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Button color', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => [
                    "default" => __("Default", "woocommerce-gateway-nexi-xpay"),
                    "black" => __("Black", "woocommerce-gateway-nexi-xpay"),
                    "white" => __("White", "woocommerce-gateway-nexi-xpay"),
                ],
                'default' => 'default',
                'desc_tip' => true,
                'class' => 'gpay-button-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, [
            'applepay_title' => [
                'title' => __('Apple Pay configuration', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'title',
                'class' => 'applepay-xpay-title',
            ],
            'applepay_label' => array(
                'title' => __('Apple Pay instructions', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'applepay_label',
                'class' => 'applepay-label',
            ),
            'applepay_integration_type' => array(
                'title' => __('Redirect or button', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Redirect or button', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => [
                    'redirect' => __('Redirect', 'woocommerce-gateway-nexi-xpay'),
                    'button' => __('Button', 'woocommerce-gateway-nexi-xpay'),
                ],
                'default' => 'redirect',
                'desc_tip' => true,
                'class' => 'applepay-xpay applepay-integration-type',
            ),
            'applepay_merchant_label' => array(
                'title' => __('Merchant label', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Merchant label', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'desc_tip' => true,
                'class' => 'applepay-xpay applepay-button-only',
            ),
            'applepay_merchant_identifier' => array(
                'title' => __('Merchant identifier', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Merchant identifier', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'desc_tip' => true,
                'class' => 'applepay-xpay applepay-button-only',
            ),
            'applepay_merchant_identifier_certificate' => [
                'title' => __('Merchant identifier certificate', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'file',
                'description' => $this->get_file_upload_name('nexi_applepay_merchant_identifier_certificate'),
                'class' => 'applepay-xpay applepay-button-only',
            ],
            'applepay_merchant_identifier_certificate_key' => [
                'title' => __('Merchant identifier certificate key', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'file',
                'description' => $this->get_file_upload_name('nexi_applepay_merchant_identifier_certificate_key'),
                'class' => 'applepay-xpay applepay-button-only',
            ],
            'applepay_ca_root_certificate' => [
                'title' => __('CA root certificate', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'file',
                'description' => $this->get_file_upload_name('nexi_applepay_ca_root_certificate'),
                'class' => 'applepay-xpay applepay-button-only',
            ],
            'applepay_button_type' => array(
                'title' => __('Button type', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Button type', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => [
                    'plain' => __('Plain', 'woocommerce-gateway-nexi-xpay'),
                    'continue' => __('Continue', 'woocommerce-gateway-nexi-xpay'),
                    'add-money' => __('Add Money', 'woocommerce-gateway-nexi-xpay'),
                    'book' => __('Book', 'woocommerce-gateway-nexi-xpay'),
                    'buy' => __('Buy', 'woocommerce-gateway-nexi-xpay'),
                    'check-out' => __('Check Out', 'woocommerce-gateway-nexi-xpay'),
                    'contribute' => __('Contribute', 'woocommerce-gateway-nexi-xpay'),
                    'donate' => __('Donate', 'woocommerce-gateway-nexi-xpay'),
                    'order' => __('Order', 'woocommerce-gateway-nexi-xpay'),
                    'pay' => __('Pay', 'woocommerce-gateway-nexi-xpay'),
                    'reload' => __('Reload', 'woocommerce-gateway-nexi-xpay'),
                    'rent' => __('Rent', 'woocommerce-gateway-nexi-xpay'),
                    'set-up' => __('Set Up', 'woocommerce-gateway-nexi-xpay'),
                    'subscribe' => __('Subscribe', 'woocommerce-gateway-nexi-xpay'),
                    'support' => __('Support', 'woocommerce-gateway-nexi-xpay'),
                    'tip' => __('Tip', 'woocommerce-gateway-nexi-xpay'),
                    'top-up' => __('Top Up', 'woocommerce-gateway-nexi-xpay'),
                ],
                'default' => 'buy',
                'desc_tip' => true,
                'class' => 'applepay-xpay applepay-button-only',
            ),
            'applepay_button_style' => array(
                'title' => __('Button style', 'woocommerce-gateway-nexi-xpay'),
                'description' => __('Button style', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => [
                    'black' => __('Black', 'woocommerce-gateway-nexi-xpay'),
                    'white-outline' => __('White with outline', 'woocommerce-gateway-nexi-xpay'),
                    'white' => __('White', 'woocommerce-gateway-nexi-xpay'),
                ],
                'default' => 'black',
                'desc_tip' => true,
                'class' => 'applepay-xpay applepay-button-only',
            ),
        ]);

        $this->form_fields = array_merge($this->form_fields, array(
            'style_title' => array(
                'title' => __('Style configuration', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'title',
                'description' => __('By using this configurator you can change the look and feel of your module', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'style_title xpay-build-only',
            ),
            'preview' => array(
                'title' => __('Preview', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'label',
                'label' => '',
                'class' => 'xpay-build-only',
                'desc_tip' => false,
            ),
            'font_family' => array(
                'title' => __('Font family', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('The font family in the CC Form', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'Arial',
                'desc_tip' => true,
                'class' => 'build_style font-family xpay-build-only',
            ),
            'font_size' => array(
                'title' => __('Font size', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('The size of the font in the CC Form in pixel', 'woocommerce-gateway-nexi-xpay'),
                'default' => '15px',
                'desc_tip' => true,
                'class' => 'build_style font-size xpay-build-only',
            ),
            'font_style' => array(
                'title' => __('Font style', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'description' => __('Font style in the CC Form', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'normal',
                'desc_tip' => true,
                'options' => $this->getOptionsConfigFontStyle(),
                'class' => 'build_style font-style xpay-build-only',
            ),
            'font_variant' => array(
                'title' => __('Font variant', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'description' => __('Font variant in the CC Form', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'normal',
                'desc_tip' => true,
                'options' => $this->getOptionsConfigFontVariant(),
                'class' => 'build_style font-variant xpay-build-only',
            ),
            'letter_spacing' => array(
                'title' => __('Letter spacing', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('The space between letters in pixel', 'woocommerce-gateway-nexi-xpay'),
                'default' => '1px',
                'desc_tip' => true,
                'class' => 'build_style letter-spacing xpay-build-only',
            ),
            'border_color_ok' => array(
                'title' => __('Border Color', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'color',
                'description' => __('When form is empty or correct', 'woocommerce-gateway-nexi-xpay'),
                'default' => '#CDCDCD',
                'desc_tip' => true,
                'css' => 'width:362px;',
                'class' => 'build_style border-color xpay-build-only',
            ),
            'border_color_ko' => array(
                'title' => __('Error Border Color', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'color',
                'description' => __('When form has error', 'woocommerce-gateway-nexi-xpay'),
                'default' => '#C80000',
                'desc_tip' => true,
                'css' => 'width:362px;',
                'class' => 'xpay-build-only',
            ),
            'placeholder_color' => array(
                'title' => __('Placeholder Color', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'color',
                'description' => __('Text color of placeholder', 'woocommerce-gateway-nexi-xpay'),
                'default' => '#CDCDCD',
                'desc_tip' => true,
                'css' => 'width:362px;',
                'class' => 'build_style placeholder-color xpay-build-only',
            ),
            'text_color' => array(
                'title' => __('Text Color', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'color',
                'description' => __('Text color in input field', 'woocommerce-gateway-nexi-xpay'),
                'default' => '#5C5C5C',
                'desc_tip' => true,
                'css' => 'width:362px;',
                'class' => 'build_style color xpay-build-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, \Nexi\WC_Pagodil_Configuration::getSettingsForm());

        if (function_exists("wcs_is_subscription")) {
            $this->form_fields = array_merge($this->form_fields, array(
                'title_section_6' => array(
                    'title' => __("Subscription configuration", 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'title',
                ),
                'nexi_xpay_recurring_enabled' => array(
                    'title' => __('Enable/Disable Recurring', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'checkbox',
                    'label' => __("Enable Nexi XPay for subscription's payment", 'woocommerce-gateway-nexi-xpay'),
                    'default' => 'no',
                    'description' => '',
                ),
                'nexi_xpay_recurring_alias' => array(
                    'title' => __('Recurring Alias', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                    'class' => 'xpay-only',
                ),
                'nexi_xpay_recurring_mac' => array(
                    'title' => __('Recurring key MAC', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                    'class' => 'xpay-only',
                ),
                'nexi_xpay_group' => array(
                    'title' => __('Group', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                    'class' => 'xpay-only',
                ),
            ));
        }
    }

    private function get_file_upload_name($key)
    {
        $value = \WC_Admin_Settings::get_option($key);

        if ($value) {
            return '<span style="padding-left:15px; margin-top:15px;">' . basename($value) . '</span>';
        }

        return "";
    }

    public function generate_gpay_label_html($key, $data)
    {
        $xpayProdGuideHtml = '<p><b>' . __('Instructions for setting up Google Pay in a production environment', 'woocommerce-gateway-nexi-xpay') . '</b></p>'
            . '<p>' . __('To enable Google Pay, follow these steps:', 'woocommerce-gateway-nexi-xpay') . '</p>'
            . '<ol>'
            . '<li>' . __('Enable APM: Verify that the alternative payment method (APM) is enabled on the terminal by accessing the portal <a href=\'https://business.nexi.it\'>https://business.nexi.it</a> in the XPay Back office -> Alternative Payments -> Google Pay section', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Register your domain: Go to the Google Pay Business Console (<a href=\'https://pay.google.com/business/console/home\'>https://pay.google.com/business/console/home</a>) and register your domain (Google Pay API -> Integrate with your website). Google must approve it before you can proceed. <i>(Note: If you don\'t have a Google Merchant account, you\'ll be able to create one during this process.)</i>', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Retrieve merchant data: Once your domain is approved, retrieve the following data from the Google Pay console:', 'woocommerce-gateway-nexi-xpay') . '<ul><li>Google Merchant Name</li><li>Google Merchant ID</li></ul></li>'
            . '<li>' . __('Set up your Gateway Merchant ID: Your Google Gateway Merchant ID is the numeric part of your alias.', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '</ol>';

        $xpayTestGuideHtml = '<p><b>' . __('Instructions for setting up Google Pay in a test environment', 'woocommerce-gateway-nexi-xpay') . '</b></p>'
            . '<p>' . __('To enable Google Pay, follow these steps:', 'woocommerce-gateway-nexi-xpay') . '</p>'
            . '<ol>'
            . '<li>' . __('Enable APM: Verify that the alternative payment method (APM) is enabled on the terminal by accessing <a href=\'https://ecommerce.nexi.it/area-test\'>https://ecommerce.nexi.it/area-test</a> -> Simple/OneClick Payment -> Back office access data -> Alternative payments -> Google Pay', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Retrieve merchant data: For the test environment, dummy data can be used to populate the Google Merchant Name (e.g. Test Merchant) and Google Merchant ID (e.g. 1234567890123456) parameters.', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Set up your Gateway Merchant ID: Your Google Gateway Merchant ID is the numeric part of your alias.', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '</ol>';

        $npgProdGuideHtml = '<p><b>' . __('Instructions for setting up Google Pay in a production environment', 'woocommerce-gateway-nexi-xpay') . '</b></p>'
            . '<p>' . __('To enable Google Pay, follow these steps:', 'woocommerce-gateway-nexi-xpay') . '</p>'
            . '<ol>'
            . '<li>' . __('Enable APM: Verify that the alternative payment method (APM) is enabled on the terminal by accessing the portal <a href=\'https://business.nexi.it/\'>https://business.nexi.it/</a> -> Services -> Ecommerce solutions -> XPay Web - Login -> Configurations -> Alternative payments -> Google Pay', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Register your domain: Go to the Google Pay Business Console (<a href=\'https://pay.google.com/business/console/home\'>https://pay.google.com/business/console/home</a>) and register your domain (Google Pay API -> Integrate with your website). Google must approve it before you can proceed. <i>(Note: If you don\'t have a Google Merchant account, you\'ll be able to create one during this process.)</i>', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Retrieve merchant data: Once your domain is approved, retrieve the following data from the Google Pay console:', 'woocommerce-gateway-nexi-xpay') . '<ul><li>Google Merchant Name</li><li>Google Merchant ID</li></ul></li>'
            . '<li>' . __('Set the Gateway Merchant ID: Set this parameter to a randomly generated UUID v4 string', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '</ol>';

        $npgTestGuideHtml = '<p><b>' . __('Instructions for setting up Google Pay in a test environment', 'woocommerce-gateway-nexi-xpay') . '</b></p>'
            . '<p>' . __('To enable Google Pay, follow these steps:', 'woocommerce-gateway-nexi-xpay') . '</p>'
            . '<ol>'
            . '<li>' . __('Enable APM: The payment method is already enabled on public test terminals (<a href=\'https://developer.nexi.it/it/area-test/api-key\'>https://developer.nexi.it/it/area-test/api-key</a>)', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Register your domain: Go to the Google Pay Business Console (<a href=\'https://pay.google.com/business/console/home\'>https://pay.google.com/business/console/home</a>) and register your domain (Google Pay API -> Integrate with your website). Google must approve it before you can proceed. <i>(Note: If you don\'t have a Google Merchant account, you\'ll be able to create one during this process.)</i>', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '<li>' . __('Retrieve merchant data: Once your domain is approved, retrieve the following data from the Google Pay console:', 'woocommerce-gateway-nexi-xpay') . '<ul><li>Google Merchant Name</li><li>Google Merchant ID</li></ul></li>'
            . '<li>' . __('Set the Gateway Merchant ID: Set this parameter to a randomly generated UUID v4 string', 'woocommerce-gateway-nexi-xpay') . '</li>'
            . '</ol>';

        $html = '';

        $html .= '<div class="nexi-xpay-admin-info google-pay-info xpay prod">' . $xpayProdGuideHtml . '</div>';
        $html .= '<div class="nexi-xpay-admin-info google-pay-info xpay test">' . $xpayTestGuideHtml . '</div>';
        $html .= '<div class="nexi-xpay-admin-info google-pay-info npg prod">' . $npgProdGuideHtml . '</div>';
        $html .= '<div class="nexi-xpay-admin-info google-pay-info npg test">' . $npgTestGuideHtml . '</div>';

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp <?php echo esc_attr($data['class']); ?>">
                <fieldset>
                    <div>
                        <?php echo $html; ?>
                    </div>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function generate_applepay_label_html($key, $data)
    {
        $xpayProdGuideHtml = '<p><b>' . __('Instructions for setting up Apple Pay in a production environment', 'woocommerce-gateway-nexi-xpay') . '</b></p>'
            . '<p>' . __('To properly set up Apple Pay, follow these steps:', 'woocommerce-gateway-nexi-xpay') . '</p>'
            . '<ol>'
            . '<li><b>' . __('Registering as an Apple Merchant', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Go to <a href=\'https://developer.apple.com/account/resources/identifiers/list/merchant\'>Apple Developer - Merchant IDs</a> to register as a merchant in your Apple Developer account.', 'woocommerce-gateway-nexi-xpay') . ' ' . __('<i>(Note: If you don\'t have an Apple Developer account, you\'ll be able to create one during this process.)</i>', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Generating certificates', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('After registration, generate the required certificates. These will need to be uploaded both to the plugin configuration and to the back office, as indicated in the following steps.', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Enabling the payment method in the Nexi back office', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Log in to the <a href=\'https://business.nexi.it\'>https://business.nexi.it</a> portal and go to XPay Back office -> Alternative payments -> Apple Pay, then in the Apple Pay S2S Management section upload one of the generated certificates.', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Uploading certificates to the plugin', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Once Apple Pay is enabled on the terminal, upload the other certificates in the plugin configuration section, following the instructions in the official documentation: <a href=\'https://developer.apple.com/help/account/capabilities/configure-apple-pay\'>https://developer.apple.com/help/account/capabilities/configure-apple-pay</a>', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Retrieving parameters', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('The Merchant ID and Merchant Name parameters are available in the merchant management section within your Apple Developer account.', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Domain Registration', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Finally, register your domain following the official Apple instructions: <a href=\'https://developer.apple.com/help/account/capabilities/configure-apple-pay-on-the-web\'>https://developer.apple.com/help/account/capabilities/configure-apple-pay-on-the-web</a>', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '</ol>';

        $xpayTestGuideHtml = '<p><b>' . __('Instructions for setting up Apple Pay in a test environment', 'woocommerce-gateway-nexi-xpay') . '</b></p>'
            . '<p>' . __('To properly set up Apple Pay, follow these steps:', 'woocommerce-gateway-nexi-xpay') . '</p>'
            . '<ol>'
            . '<li><b>' . __('Registering as an Apple Merchant', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Go to <a href=\'https://developer.apple.com/account/resources/identifiers/list/merchant\'>Apple Developer - Merchant IDs</a> to register as a merchant in your Apple Developer account.', 'woocommerce-gateway-nexi-xpay') . ' ' . __('<i>(Note: If you don\'t have an Apple Developer account, you\'ll be able to create one during this process.)</i>', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Generating certificates', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('After registration, generate the required certificates. These will need to be uploaded both to the plugin configuration and to the back office, as indicated in the following steps.', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Enabling the payment method in the Nexi back office', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Access the test area at the link <a href=\'https://ecommerce.nexi.it/area-test\'>https://ecommerce.nexi.it/area-test</a> and go to Simple Payment/OneClick->Back office access data->Alternative payments->Apple Pay, then in the Apple Pay S2S Management section upload one of the generated certificates.', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Uploading certificates to the plugin', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Once Apple Pay is enabled on the terminal, upload the other certificates in the plugin configuration section, following the instructions in the official documentation: <a href=\'https://developer.apple.com/help/account/capabilities/configure-apple-pay\'>https://developer.apple.com/help/account/capabilities/configure-apple-pay</a>', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Retrieving parameters', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('The Merchant ID and Merchant Name parameters are available in the merchant management section within your Apple Developer account.', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '<li><b>' . __('Domain Registration', 'woocommerce-gateway-nexi-xpay') . '</b><br />'
            . '<p>' . __('Finally, register your domain following the official Apple instructions: <a href=\'https://developer.apple.com/help/account/capabilities/configure-apple-pay-on-the-web\'>https://developer.apple.com/help/account/capabilities/configure-apple-pay-on-the-web</a>', 'woocommerce-gateway-nexi-xpay') . '</p></li>'
            . '</ol>';

        $html = '';

        $html .= '<div class="nexi-xpay-admin-info apple-pay-info prod">' . $xpayProdGuideHtml . '</div>';
        $html .= '<div class="nexi-xpay-admin-info apple-pay-info test">' . $xpayTestGuideHtml . '</div>';

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp <?php echo esc_attr($data['class']); ?>">
                <fieldset>
                    <div>
                        <?php echo $html; ?>
                    </div>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Field group HTML.
     *
     * @param mixed $key
     * @param mixed $data
     * @return string
     */
    public function generate_field_group_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;

        $value = $this->get_option($key);

        if ($value === false || $value === null || $value === "") {
            $value = $data['default'];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp <?php echo esc_attr($data['class']); ?>">
                <fieldset>
                    <div id="installments-ranges-variations-container">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo __('Up to an amount of', 'woocommerce-gateway-nexi-xpay'); ?></th>
                                    <th><?php echo __('Maximum installments', 'woocommerce-gateway-nexi-xpay'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div>
                        <button class="button"
                            id="add-ranges-variation"><?php echo __('Add rule', 'woocommerce-gateway-nexi-xpay'); ?></button>
                    </div>

                    <input type="hidden" id="ranges-delete-label"
                        value="<?php echo __('Delete', 'woocommerce-gateway-nexi-xpay'); ?>" />

                    <input type="hidden" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($field); ?>"
                        value="<?php echo esc_attr($value); ?>" />
                </fieldset>

                <style>
                    #installments-ranges-variations-container table thead th,
                    #installments-ranges-variations-container table tbody td {
                        padding: 5px 10px;
                        padding-left: 0;
                        width: 200px;
                    }

                    #installments-ranges-variations-container table tbody td input {
                        width: 190px;
                    }

                    #installments-ranges-variations-container {
                        margin-bottom: 20px;
                    }
                </style>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Label HTML.
     *
     * @param mixed $key
     * @param mixed $data
     * @return string
     */
    public function generate_label_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp <?php echo esc_attr($data['class']); ?>">
                <fieldset>
                    <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['label']); ?></label>

                    <?php echo $this->get_description_html($data); ?>

                    <div>
                        <?php include_once plugin_dir_path(WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE) . 'templates/build_preview.php'; ?>
                    </div>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Label HTML.
     *
     * @param mixed $key
     * @param mixed $data
     * @return string
     */
    public function generate_simple_label_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <label for="<?php echo esc_attr($field); ?>"
                        class="<?php echo wp_kses_post($data['class']); ?>"><?php echo wp_kses_post($data['label']); ?></label>

                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    private function getOptionsConfigFontStyle()
    {
        return array(
            "normal" => "normal",
            "italic" => "italic",
            "oblique" => "oblique",
            "initial" => "initial",
            "inherit" => "inherit"
        );
    }

    private function getOptionsConfigFontVariant()
    {
        return array(
            "normal" => "normal",
            "small-caps" => "small-caps",
            "initial" => "initial",
            "inherit" => "inherit"
        );
    }

}
