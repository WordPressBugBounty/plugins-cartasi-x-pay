<?php

/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     7.2.2
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */

namespace Nexi;

class WC_Nexi_Helper
{

    public static function cart_contains_subscription()
    {
        return class_exists("\WC_Subscriptions_Cart") && \WC_Subscriptions_Cart::cart_contains_subscription();
    }

    public static function order_or_cart_contains_subscription($order)
    {
        return (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) || self::cart_contains_subscription();
    }

    /**
     * checks if the requested information is available as per new configuration format and returns it
     * if it is not available in the new format it is searched in the old one
     *
     * @param int $order_id
     * @param string $key   'alias' | 'scadenza_pan' | 'num_contratto' | 'codTrans'
     * @return mixed
     */
    public static function get_xpay_post_meta($order_id, $key)
    {
        $ret = \Nexi\OrderHelper::getOrderMeta($order_id, '_xpay_' . $key, true);

        if ($ret != "") {
            return $ret;
        }

        $xpay_details_order = \Nexi\OrderHelper::getOrderMeta($order_id, "xpay_details_order", true);

        if ($xpay_details_order != "") {
            $details_order = json_decode($xpay_details_order);

            \Nexi\OrderHelper::updateOrderMeta($order_id, "_xpay_alias", $details_order->alias);
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_xpay_scadenza_pan", $details_order->scadenza_pan);
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_xpay_num_contratto", $details_order->num_contratto);
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_xpay_codTrans", $details_order->codTrans);

            return $details_order->{$key};
        }

        return "";
    }

    /**
     * checks if bcmath is available and returns the divison result calculated with its function, otherwise returns the result calculated with basic arithmetic operators
     *
     * @param int|float|string $dividend
     * @param int|float|string $divisor
     * @param integer $decimals
     * @return float
     */
    public static function div_bcdiv($dividend, $divisor, $decimals = 2)
    {
        if (extension_loaded('bcmath')) {
            return (float) bcdiv((string) $dividend, (string) $divisor, $decimals);
        }

        return round($dividend / $divisor, $decimals);
    }

    /**
     * checks if bcmath is available and returns the multiplication result calculated with its function, otherwise returns the result calculated with basic arithmetic operators
     *
     * @param int|float|string $num1
     * @param int|float|string $num2
     * @param integer $decimals
     * @return float
     */
    public static function mul_bcmul($num1, $num2, $decimals = 2)
    {
        if (extension_loaded('bcmath')) {
            return (float) bcmul((string) $num1, (string) $num2, $decimals);
        }

        return round($num1 * $num2, $decimals);
    }

    /**
     *
     * @param [type] $template
     * @return string
     */
    public static function get_nexi_template_path($template)
    {
        $path = plugin_dir_path(WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE);

        return $path . 'templates/' . $template;
    }

    public static function get_nexi_settings()
    {
        $config = get_option(WC_SETTINGS_KEY);

        if (!is_array($config)) {
            $config = [
                'nexi_gateway' => GATEWAY_XPAY,
                'integration_type' => 'redirect',
            ];
        }

        return $config;
    }

    public static function nexi_is_gateway_NPG($config = null)
    {
        if ($config === null) {
            $config = static::get_nexi_settings();
        }

        return static::nexi_array_key_exists_and_equals($config, 'nexi_gateway', GATEWAY_NPG);
    }

    public static function nexi_is_gateway_XPay($config = null)
    {
        if ($config === null) {
            $config = static::get_nexi_settings();
        }

        return static::nexi_array_key_exists_and_equals($config, 'nexi_gateway', GATEWAY_XPAY);
    }

    public static function nexi_is_build($config = null)
    {
        if ($config === null) {
            $config = static::get_nexi_settings();
        }

        return static::nexi_array_key_exists_and_equals($config, 'integration_type', 'build');
    }

    public static function is_google_button_enabled($config = null)
    {
        if ($config === null) {
            $config = static::get_nexi_settings();
        }

        return static::nexi_array_key_exists_and_equals($config, 'gpay_integration_type', 'button');
    }

    public static function is_apple_button_enabled($config = null)
    {
        if ($config === null) {
            $config = static::get_nexi_settings();
        }

        return static::nexi_array_key_exists_and_equals($config, 'applepay_integration_type', 'button');
    }

    public static function nexi_array_key_exists($array, $key)
    {
        return isset($array) && is_array($array) && array_key_exists($key, $array);
    }

    public static function nexi_array_key_exists_and_equals($array, $key, $value)
    {
        return static::nexi_array_key_exists($array, $key) && $array[$key] == $value;
    }

    public static function nexi_array_key_exists_and_in_array($array, $key, $value)
    {
        return static::nexi_array_key_exists($array, $key) && in_array($value, $array[$key]);
    }

    public static function get_xpay_cards()
    {
        $availableMethods = \Nexi\WC_Nexi_Helper::get_xpay_available_methods();

        $cards = [
            'MASTERCARD',
            'MAESTRO',
            'VISA',
            'AMEX',
            'JCB',
            'UPI',
            'DINERS',
        ];

        foreach ($availableMethods as $i => $availableMethod) {
            if ($availableMethod['type'] === 'CC') {
                $order = array_search($availableMethod['code'], $cards);

                $availableMethods[$i]['order'] = $order !== false ? $order : 100;
            } else {
                unset($availableMethods[$i]);
            }
        }

        array_multisort(array_column($availableMethods, 'order'), SORT_ASC, array_column($availableMethods, 'description'), SORT_ASC, $availableMethods);

        return $availableMethods;
    }

    public static function get_npg_cards()
    {
        $availableMethods = \Nexi\WC_Nexi_Helper::get_npg_available_methods();

        $cards = [
            'MC',
            'MAE',
            'VISA',
            'AMEX',
            'JCB',
            'UPI',
            'DINERS',
        ];

        foreach ($availableMethods as $i => $availableMethod) {
            if ($availableMethod['paymentMethodType'] === 'CARDS') {
                $order = array_search($availableMethod['circuit'], $cards);

                $availableMethods[$i]['order'] = $order !== false ? $order : 100;
            } else {
                unset($availableMethods[$i]);
            }
        }

        array_multisort(array_column($availableMethods, 'order'), SORT_ASC, array_column($availableMethods, 'circuit'), SORT_ASC, $availableMethods);

        return $availableMethods;
    }

    public static function get_xpay_available_methods()
    {
        $availableMethods = json_decode(\WC_Admin_Settings::get_option('xpay_available_methods') ?? '[]', true);

        return is_array($availableMethods) ? $availableMethods : [];
    }

    public static function get_npg_available_methods()
    {
        $availableMethods = json_decode(\WC_Admin_Settings::get_option('xpay_npg_available_methods') ?? '[]', true);

        return is_array($availableMethods) ? $availableMethods : [];
    }

    public static function enable_payment_method($key)
    {
        $config = get_option($key, []);

        if (!isset($config) || !isset($config['enabled'])) {
            $config['enabled'] = 'yes';
        }

        update_option($key, $config);
    }

    public static function get_safe_get_params()
    {
        $safe_vars = [];

        foreach ($_GET as $key => $value) {
            $safe_vars[$key] = sanitize_text_field($value);
        }

        return $safe_vars;
    }

}
