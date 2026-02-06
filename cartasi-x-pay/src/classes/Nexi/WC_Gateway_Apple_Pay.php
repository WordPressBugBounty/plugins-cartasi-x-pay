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

class WC_Gateway_Apple_Pay
{

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

    public static function apple_pay_validate_merchant()
    {
        $connection = curl_init();

        if (!$connection) {
            throw new \Exception(__('Can\'t connect!', 'woocommerce-gateway-nexi-xpay'));
        }

        $validationUrl = filter_input(INPUT_POST, 'validation_url') ?? $_POST['validation_url'];

        $currentConfig = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        $request = [
            'merchantIdentifier' => $currentConfig['applepay_merchant_identifier'],
            'displayName' => $currentConfig['applepay_merchant_label'],
            'initiative' => "web",
            'initiativeContext' => str_replace(["http://", "https://", "/"], "", get_site_url()),
        ];

        curl_setopt_array($connection, [
            CURLOPT_URL => $validationUrl,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_TIMEOUT => 25,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_SSLCERT => \WC_Admin_Settings::get_option('nexi_applepay_merchant_identifier_certificate'),
            CURLOPT_SSLKEY => \WC_Admin_Settings::get_option('nexi_applepay_merchant_identifier_certificate_key'),
            CURLOPT_CAINFO => \WC_Admin_Settings::get_option('nexi_applepay_ca_root_certificate'),
        ]);

        $response = curl_exec($connection);

        if ($response == false) {
            throw new \Exception(sprintf(__('CURL exec error: %s', 'woocommerce-gateway-nexi-xpay'), curl_error($connection)));
        }

        curl_close($connection);

        wp_send_json(json_decode($response));
    }

}
