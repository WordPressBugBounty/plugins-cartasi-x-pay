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

class WC_Gateway_Google_Pay
{

    public static function google_pay_configuration()
    {
        $currentConfig = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        $country_code = 'IT';

        if (function_exists('WC') && WC()->customer) {
            $country_code = WC()->customer->get_shipping_country() ?? $country_code;
        }

        $cardsMapping = [
            'MASTERCARD' => 'MASTERCARD',
            'MC' => 'MASTERCARD',
            'VISA' => 'VISA',
            'JCB' => 'JCB',
            'AMEX' => 'AMEX',
        ];

        $cards = [];

        if (\Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
            $gateway = 'nexigtw';
            $testMode = false;

            foreach (\Nexi\WC_Nexi_Helper::get_npg_cards() as $am) {
                if (isset($cardsMapping[$am['circuit']])) {
                    $cards[] = $cardsMapping[$am['circuit']];
                }
            }
        } else {
            $gateway = 'nexi';
            $testMode = $currentConfig['nexi_xpay_test_mode'] === "yes";

            foreach (\Nexi\WC_Nexi_Helper::get_xpay_cards() as $am) {
                if (isset($cardsMapping[$am['code']])) {
                    $cards[] = $cardsMapping[$am['code']];
                }
            }
        }

        $data = [
            "config" => [
                "gateway" => $gateway,
                "test_mode" => $testMode,
                "merchant_name" => $currentConfig['gpay_merchant_name'],
                "merchant_id" => $currentConfig['gpay_merchant_id'],
                "gateway_merchant_id" => $currentConfig['gpay_gateway_merchant_id'],
                "button_color" => $currentConfig['gpay_button_color'],
                "button_type" => $currentConfig['gpay_button_type'],
                "button_locale" => substr(get_locale(), 0, 2),
            ],
            "cards" => $cards,
            "transactionInfo" => [
                "countryCode" => $country_code,
                "currencyCode" => get_woocommerce_currency(),
                "totalPrice" => (string) round(WC()->cart->total, 2),
            ],
        ];

        wp_send_json($data);
    }

}
