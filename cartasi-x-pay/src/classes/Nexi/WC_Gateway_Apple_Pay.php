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

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Apple_Pay
{
    private static function isAllowedAppleValidationHost($host)
    {
        $allowedSuffixes = [
            'apple.com',
            'apple-pay-gateway.apple.com',
            'apple-pay-gateway-cert.apple.com',
        ];

        $host = strtolower((string) $host);
        foreach ($allowedSuffixes as $suffix) {
            if ($host === $suffix || substr($host, -strlen('.' . $suffix)) === '.' . $suffix) {
                return true;
            }
        }

        return false;
    }

    public static function apple_pay_configuration()
    {
        $currentConfig = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        $country_code = 'IT';

        if (function_exists('WC') && WC()->customer) {
            $country_code = WC()->customer->get_shipping_country() ?? $country_code;
        }

        $cardsMapping = [
            'MAESTRO' => 'maestro',
            'MASTERCARD' => 'masterCard',
            'MC' => 'masterCard',
            'VISA' => 'visa',
            'JCB' => 'jcb',
            'AMEX' => 'amex',
        ];

        $cards = [];

        if (\Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
            foreach (\Nexi\WC_Nexi_Helper::get_npg_cards() as $am) {
                if (isset($cardsMapping[$am['circuit']])) {
                    $cards[] = $cardsMapping[$am['circuit']];
                }
            }
        } else {
            foreach (\Nexi\WC_Nexi_Helper::get_xpay_cards() as $am) {
                if (isset($cardsMapping[$am['code']])) {
                    $cards[] = $cardsMapping[$am['code']];
                }
            }
        }

        $data = [
            "button_style" => $currentConfig['applepay_button_style'],
            "button_type" => $currentConfig['applepay_button_type'],
            "button_locale" => get_locale(),
            "config" => [
                "test_mode" => $currentConfig['nexi_xpay_test_mode'] === "yes",
                "merchantLabel" => $currentConfig['applepay_merchant_label'],
            ],
            "cards" => $cards,
            "transactionInfo" => [
                "countryCode" => $country_code,
                "currencyCode" => get_woocommerce_currency(),
                "totalAmount" => (string) round(WC()->cart->total, 2),
            ],
        ];

        wp_send_json($data);
    }

    public static function add_apple_pay_certificates(&$handle, $args)
    {
        if (!isset($args['_add_certs'])) {
            return;
        }

        $certs = [
            'cert' => \WC_Admin_Settings::get_option('nexi_applepay_merchant_identifier_certificate'), // file path or string blob
            'key' => \WC_Admin_Settings::get_option('nexi_applepay_merchant_identifier_certificate_key'), // same as above
            'cainfo' => \WC_Admin_Settings::get_option('nexi_applepay_ca_root_certificate'), // same as above
        ];

        curl_setopt($handle, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($handle, is_readable($certs['cert']) ? CURLOPT_SSLCERT : CURLOPT_SSLCERT_BLOB, $certs['cert']);
        curl_setopt($handle, is_readable($certs['key']) ? CURLOPT_SSLKEY : CURLOPT_SSLKEY_BLOB, $certs['key']);
        curl_setopt($handle, is_readable($certs['key']) ? CURLOPT_SSLKEY : CURLOPT_SSLKEY_BLOB, $certs['key']);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
    }

    public static function apple_pay_validate_merchant()
    {
        $validationUrl = filter_input(INPUT_POST, 'validation_url') ?? $_POST['validation_url'];
        $parsedUrl = wp_parse_url($validationUrl);

        if (
            !$parsedUrl ||
            !isset($parsedUrl['scheme']) ||
            !isset($parsedUrl['host']) ||
            strtolower($parsedUrl['scheme']) !== 'https' ||
            !self::isAllowedAppleValidationHost($parsedUrl['host'])
        ) {
            wp_send_json_error(['message' => __('Invalid validation URL.', 'woocommerce-gateway-nexi-xpay')], 400);
        }

        $baseHeaders = [
            'Content-Type' => 'application/json'
        ];

        $currentConfig = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        $request = [
            'merchantIdentifier' => $currentConfig['applepay_merchant_identifier'],
            'displayName' => $currentConfig['applepay_merchant_label'],
            'initiative' => "web",
            'initiativeContext' => str_replace(["http://", "https://", "/"], "", get_site_url()),
        ];

        $args = [
            'method' => 'POST',
            'timeout' => 25,
            'headers' => $baseHeaders,
            'body' => json_encode($request),
        ];

        $parsedArgs = wp_parse_args($args, [
            '_add_certs' => true,
        ]);

        add_action('http_api_curl', '\Nexi\WC_Gateway_Apple_Pay::add_apple_pay_certificates', 10, 2);

        try {
            Log::actionInfo(__FUNCTION__ . ' executing wp_remote_request()');
            $response = wp_remote_request($validationUrl, $parsedArgs);
        } catch (\Exception $e) {
            // translators: 1: exception message.
            throw new \Exception(esc_html(sprintf(__('CURL exec error: %1$s', 'woocommerce-gateway-nexi-xpay'), $e->getMessage())));
        }

        remove_action('http_api_curl', '\Nexi\WC_Gateway_Apple_Pay::add_apple_pay_certificates', 10);

        wp_send_json(json_decode($response['body'], true));
    }

}
