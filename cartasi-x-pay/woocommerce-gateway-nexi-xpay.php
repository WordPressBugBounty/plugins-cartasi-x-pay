<?php

/**
 * Plugin Name: Nexi XPay
 * Plugin URI:
 * Description: Payment plugin for payment cards and alternative methods. Powered by Nexi.
 * Version: 8.2.0
 * Author: Nexi SpA
 * Author URI: https://www.nexi.it
 * Domain Path: /lang
 * Text Domain: woocommerce-gateway-nexi-xpay
 * Copyright: © 2017-2024, Nexi SpA
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active_for_network')) {
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');
}

add_action('plugins_loaded', 'nexi_xpay_plugins_loaded');

function nexi_xpay_plugins_loaded()
{
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || is_plugin_active_for_network('woocommerce/woocommerce.php')) {
        define("WC_GATEWAY_XPAY_VERSION", "8.2.0");

        define("GATEWAY_XPAY", "xpay");
        define("GATEWAY_NPG", "npg");

        define('WC_SETTINGS_KEY', 'woocommerce_xpay_settings');

        define('NPG_OR_AUTHORIZED', 'AUTHORIZED');
        define('NPG_OR_EXECUTED', 'EXECUTED');
        define('NPG_OR_DECLINED', 'DECLINED');
        define('NPG_OR_DENIED_BY_RISK', 'DENIED_BY_RISK');
        define('NPG_OR_THREEDS_VALIDATED', 'THREEDS_VALIDATED');
        define('NPG_OR_THREEDS_FAILED', 'THREEDS_FAILED');
        define('NPG_OR_3DS_FAILED', '3DS_FAILED');
        define('NPG_OR_PENDING', 'PENDING');
        define('NPG_OR_CANCELED', 'CANCELED');
        define('NPG_OR_CANCELLED', 'CANCELLED');
        define('NPG_OR_VOIDED', 'VOIDED');
        define('NPG_OR_REFUNDED', 'REFUNDED');
        define('NPG_OR_FAILED', 'FAILED');
        define('NPG_OR_EXPIRED', 'EXPIRED');

        define('WC_GATEWAY_XPAY_PLUGIN_BASE_PATH', plugin_dir_path(__FILE__));
        define('WC_GATEWAY_XPAY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

        define('NPG_PAYMENT_SUCCESSFUL', [
            NPG_OR_AUTHORIZED,
            NPG_OR_EXECUTED,
        ]);

        define('NPG_PAYMENT_FAILURE', [
            NPG_OR_DECLINED,
            NPG_OR_DENIED_BY_RISK,
            NPG_OR_FAILED,
            NPG_OR_THREEDS_FAILED,
            NPG_OR_3DS_FAILED,
        ]);

        define('NPG_CONTRACT_CIT', 'CIT');

        define('NPG_OT_AUTHORIZATION', 'AUTHORIZATION');
        define('NPG_OT_CAPTURE', 'CAPTURE');
        define('NPG_OT_VOID', 'VOID');
        define('NPG_OT_REFUND', 'REFUND');
        define('NPG_OT_CANCEL', 'CANCEL');

        define('NPG_NO_RECURRING', 'NO_RECURRING');
        define('NPG_SUBSEQUENT_PAYMENT', 'SUBSEQUENT_PAYMENT');
        define('NPG_CONTRACT_CREATION', 'CONTRACT_CREATION');
        define('NPG_CARD_SUBSTITUTION', 'CARD_SUBSTITUTION');

        define('NPG_RT_MIT_UNSCHEDULED', 'MIT_UNSCHEDULED');

        load_plugin_textdomain('woocommerce-gateway-nexi-xpay', false, dirname(plugin_basename(__FILE__)) . '/lang');

        include_once realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . "autoload.php";

        add_filter('woocommerce_payment_gateways', "\Nexi\WC_Gateway_Nexi_Register_Available::register");

        add_filter('woocommerce_available_payment_gateways', "\Nexi\WC_Gateway_Nexi_Register_Available::filter_available_payment_gateways");

        // Register endpoint in the rest api for s2s notification API, for post payment redirect url and for cancel url
        add_action('rest_api_init', '\Nexi\WC_Gateway_XPay_Process_Completion::rest_api_init');
        add_action('rest_api_init', '\Nexi\WC_Gateway_NPG_Process_Completion::rest_api_init');

        \Nexi\WC_Pagodil_Widget::register();

        add_action('wp_ajax_validate_checkout_form', '\Nexi\WC_XPay_Checkout::validate_checkout_form');
        add_action('wp_ajax_nopriv_validate_checkout_form', '\Nexi\WC_XPay_Checkout::validate_checkout_form');

        add_action('wp_ajax_get_build_fields', '\Nexi\WC_Gateway_NPG_Cards_Build::get_build_fields');
        add_action('wp_ajax_nopriv_get_build_fields', '\Nexi\WC_Gateway_NPG_Cards_Build::get_build_fields');

        add_action('wp_ajax_build_payment_payload', '\Nexi\WC_Gateway_XPay_Cards_Build::build_payment_payload');
        add_action('wp_ajax_nopriv_build_payment_payload', '\Nexi\WC_Gateway_XPay_Cards_Build::build_payment_payload');

        add_action('wp_ajax_google_pay_configuration', '\Nexi\WC_Gateway_Google_Pay::google_pay_configuration');
        add_action('wp_ajax_nopriv_google_pay_configuration', '\Nexi\WC_Gateway_Google_Pay::google_pay_configuration');

        add_action('wp_ajax_apple_pay_configuration', '\Nexi\WC_Gateway_Apple_Pay::apple_pay_configuration');
        add_action('wp_ajax_nopriv_apple_pay_configuration', '\Nexi\WC_Gateway_Apple_Pay::apple_pay_configuration');

        add_action('wp_ajax_apple_pay_validate_merchant', '\Nexi\WC_Gateway_Apple_Pay::apple_pay_validate_merchant');
        add_action('wp_ajax_nopriv_apple_pay_validate_merchant', '\Nexi\WC_Gateway_Apple_Pay::apple_pay_validate_merchant');

        define('WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE', __FILE__);

        function xpay_gw_wp_enqueue_scripts()
        {
            wp_enqueue_script('xpay-checkout', plugins_url('assets/js/xpay.js', __FILE__), array('jquery'), WC_GATEWAY_XPAY_VERSION);
            wp_enqueue_style('xpay-checkout', plugins_url('assets/css/xpay.css', __FILE__), [], WC_GATEWAY_XPAY_VERSION);

            $isGoogleButtonEnabled = \Nexi\WC_Nexi_Helper::is_google_button_enabled();

            if ($isGoogleButtonEnabled) {
                wp_enqueue_script('google-pay-js', 'https://pay.google.com/gp/p/js/pay.js');
            }

            $isAppleButtonEnabled = \Nexi\WC_Nexi_Helper::is_apple_button_enabled();

            if ($isAppleButtonEnabled) {
                wp_enqueue_script('apple-pay-js', 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js');
            }

            if (!wooommerce_has_block_checkout()) {
                $isBuild = \Nexi\WC_Nexi_Helper::nexi_is_build();
                $isNpg = \Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG();
                $isXPay = \Nexi\WC_Nexi_Helper::nexi_is_gateway_XPay();

                if ($isGoogleButtonEnabled) {
                    if ($isNpg) {
                        wp_enqueue_script('xpay-npg-google-pay-js', plugins_url('assets/js/xpay-googlepay-npg.js', __FILE__), [], WC_GATEWAY_XPAY_VERSION);
                    } else {
                        wp_enqueue_script('xpay-google-pay-js', plugins_url('assets/js/xpay-googlepay.js', __FILE__), [], WC_GATEWAY_XPAY_VERSION);
                    }
                }

                if ($isAppleButtonEnabled) {
                    if ($isXPay) {
                        wp_enqueue_script('xpay-apple-pay-js', plugins_url('assets/js/xpay-applepay.js', __FILE__), [], WC_GATEWAY_XPAY_VERSION);
                    }
                }

                if ($isBuild) {
                    if ($isNpg) {
                        wp_enqueue_script('xpay-build-npg-checkout', plugins_url('assets/js/xpay-build-npg.js', __FILE__), array('jquery'), WC_GATEWAY_XPAY_VERSION);
                    } else {
                        wp_enqueue_script('xpay-build-checkout', plugins_url('assets/js/xpay-build.js', __FILE__), array('jquery'), WC_GATEWAY_XPAY_VERSION);
                    }
                }
            }
        }

        add_action('admin_init', '\Nexi\WC_Admin_Page::init');

        add_action('admin_init', '\Nexi\WC_Admin_Redirect::init');

        add_action('wp_enqueue_scripts', 'xpay_gw_wp_enqueue_scripts');

        if (!defined("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION")) {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins = get_plugins();

            define("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION", $plugins["woocommerce/woocommerce.php"]["Version"]);
        }

        if (!defined("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL")) {
            $default_logo_url = plugins_url('assets/images/logo.jpg', plugin_basename(__FILE__));

            define("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL", $default_logo_url);
        }

        // custom hook called by the scheduled cron
        add_action('wp_nexi_polling', 'wp_nexi_polling_executor');

        function wp_nexi_polling_executor()
        {
            $args = array(
                'payment_method' => 'xpay',
                'status' => ['wc-pending'],
                'orderby' => 'date',
                'order' => 'ASC',
            );

            $orders = wc_get_orders($args);

            foreach ($orders as $order) {
                $authorizationRecord = \Nexi\WC_Gateway_NPG_API::getInstance()->get_order_status($order->get_id());

                if ($authorizationRecord === null) {
                    \Nexi\Log::actionWarning(__FUNCTION__ . ': authorization operation not found for order: ' . $order->get_id());
                    continue;
                }

                $orderObj = new \WC_Order($order->get_id());

                switch ($authorizationRecord['operationResult']) {
                    case NPG_OR_AUTHORIZED:
                    case NPG_OR_EXECUTED:
                        $completed = $orderObj->payment_complete(\Nexi\OrderHelper::getOrderMeta($order->get_id(), "_npg_orderId", true));

                        if ($completed) {
                            \Nexi\WC_Save_Order_Meta::saveSuccessNpg(
                                $order->get_id(),
                                $authorizationRecord
                            );
                        } else {
                            \Nexi\Log::actionWarning(__FUNCTION__ . ': unable to change order status: ' . $orderObj->get_status());
                        }
                        break;

                    case NPG_OR_PENDING:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': operation not in a final status yet');
                        break;

                    case NPG_OR_CANCELED:
                    case NPG_OR_CANCELLED:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': payment canceled');

                        if ($order->get_status() != 'cancelled') {
                            $order->update_status('cancelled');
                        }
                        break;

                    case NPG_OR_DECLINED:
                    case NPG_OR_DENIED_BY_RISK:
                    case NPG_OR_THREEDS_FAILED:
                    case NPG_OR_3DS_FAILED:
                    case NPG_OR_FAILED:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - operation: ' . json_encode($authorizationRecord));

                        if ($order->get_status() != 'cancelled') {
                            $orderObj->update_status('failed');
                        }

                        $orderObj->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay'));
                        break;

                    default:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - not managed operation status: ' . json_encode($authorizationRecord));
                        break;
                }
            }
        }

        add_action('wp_nexi_update_npg_payment_methods', 'wp_nexi_update_npg_payment_methods_executor');

        function wp_nexi_update_npg_payment_methods_executor()
        {
            try {
                \Nexi\WC_Gateway_NPG_API::getInstance()->get_profile_info();
            } catch (\Exception $exc) {
                \Nexi\Log::actionWarning(__FUNCTION__ . $exc->getMessage());
            }
        }

        // to add a new custom interval for cron execution
        function my_add_nexi_schedules_for_polling($schedules)
        {
            // add a 'nexi_polling_schedule' schedule to the existing set
            $schedules['nexi_polling_schedule'] = array(
                'interval' => 300,
                'display' => __('5 minutes')
            );

            $schedules['nexi_polling_schedule_2h'] = array(
                'interval' => 7200,
                'display' => __('2 hours'),
            );

            return $schedules;
        }

        add_filter('cron_schedules', 'my_add_nexi_schedules_for_polling');

        //chcks if the task is not already scheduled
        if (!wp_next_scheduled('wp_nexi_polling') && !\Nexi\WC_Nexi_Helper::nexi_is_build() && \Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
            //schedules the task by giving the first execution time, the interval and the hook to call
            wp_schedule_event(time(), 'nexi_polling_schedule', 'wp_nexi_polling');
        }

        if (!wp_next_scheduled('wp_nexi_update_npg_payment_methods') && !\Nexi\WC_Nexi_Helper::nexi_is_build() && \Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
            //schedules the task by giving the first execution time, the interval and the hook to call
            wp_schedule_event(time(), 'nexi_polling_schedule_2h', 'wp_nexi_update_npg_payment_methods');
        }

        function xpay_plugin_activation()
        {
            $nexi_unique = get_option("nexi_unique");

            if ($nexi_unique == "") {
                update_option('nexi_unique', uniqid());
            }
        }

        register_activation_hook(__FILE__, 'xpay_plugin_activation');

        function xpay_plugin_deactivation()
        {
            $timestamp = wp_next_scheduled('wp_nexi_polling');

            if ($timestamp) {
                wp_unschedule_event($timestamp, 'wp_nexi_polling');
            }

            $timestamp = wp_next_scheduled('wp_nexi_update_npg_payment_methods');

            if ($timestamp) {
                wp_unschedule_event($timestamp, 'wp_nexi_update_npg_payment_methods');
            }
        }

        register_deactivation_hook(__FILE__, 'xpay_plugin_deactivation');

        function xpay_plugin_action_links($links)
        {
            $plugin_links = array(
                '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=xpay')) . '">' . __('Settings') . '</a>',
            );

            return array_merge($plugin_links, $links);
        }

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'xpay_plugin_action_links');

        add_action('init', function () {
            \Nexi\WC_Pending_Status::addNexiPendingPaymentOrderStatus();

            \Nexi\WC_Nexi_Db::run_updates();

            add_rewrite_endpoint('woocommerce-gateway-nexi-xpay-redirect', EP_ROOT | EP_PAGES);
            add_rewrite_endpoint('woocommerce-gateway-nexi-xpay-cancel', EP_ROOT | EP_PAGES);
            add_rewrite_endpoint('woocommerce-gateway-nexi-npg-redirect', EP_ROOT | EP_PAGES);
            add_rewrite_endpoint('woocommerce-gateway-nexi-npg-cancel', EP_ROOT | EP_PAGES);
            add_rewrite_endpoint('woocommerce-gateway-nexi-npg-googlepay-redirect', EP_ROOT | EP_PAGES);

            $option_value = get_option("nexi_xpay_redirect_flush_rewrite_rule", false);

            if ($option_value === false) {
                flush_rewrite_rules();

                update_option("nexi_xpay_redirect_flush_rewrite_rule", "1");
            }
        });

        add_filter('query_vars', function ($query_vars) {
            $query_vars[] = 'woocommerce-gateway-nexi-xpay-redirect';
            $query_vars[] = 'woocommerce-gateway-nexi-xpay-cancel';
            $query_vars[] = 'woocommerce-gateway-nexi-npg-redirect';
            $query_vars[] = 'woocommerce-gateway-nexi-npg-cancel';
            $query_vars[] = 'woocommerce-gateway-nexi-npg-googlepay-redirect';
            $query_vars[] = 'nexi_order_id';

            return $query_vars;
        });

        add_action('template_redirect', function () {
            \Nexi\WC_Gateway_XPay_Process_Completion::action_template_redirect();

            \Nexi\WC_Gateway_NPG_Process_Completion::action_template_redirect();
        });

        add_filter('wc_order_statuses', '\Nexi\WC_Pending_Status::wcOrderStatusesFilter');

        add_filter('woocommerce_valid_order_statuses_for_payment_complete', '\Nexi\WC_Pending_Status::validOrderStatusesForPaymentCompleteFilter');

        add_action('woocommerce_payment_token_deleted', '\Nexi\WC_Gateway_NPG_Cards_Redirect::woocommerce_payment_token_deleted', 10, 2);

        function nexixpay_admin_warning()
        {
            if (!extension_loaded('bcmath')) {
                $notice = '
                <div class="notice notice-warning">
                    <p><b>Nexi XPay</b>: ' . __('Warning, the PHP extension bcmath is not enabled. The amounts calculated by the plugin may be incorrect; please enable it to ensure correct calculations.', 'woocommerce-gateway-nexi-xpay') . '</p>
                </div>
            ';

                echo $notice;
            }
        }

        add_action('admin_notices', 'nexixpay_admin_warning');
    }
}

add_action(
    'before_woocommerce_init',
    function () {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
);

add_action('woocommerce_blocks_loaded', 'woocommerce_gateway_nexi_xpay_woocommerce_block_support');

function woocommerce_gateway_nexi_xpay_woocommerce_block_support()
{
    if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                foreach (\Nexi\WC_Gateway_Nexi_Register_Available::registerBlocks() as $paymentMethod) {
                    $payment_method_registry->register($paymentMethod);
                }
            }
        );
    }
}

function wooommerce_has_block_checkout()
{
    if (class_exists('\WC_Blocks_Utils')) {
        return \WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
    } else {
        return false;
    }
}
