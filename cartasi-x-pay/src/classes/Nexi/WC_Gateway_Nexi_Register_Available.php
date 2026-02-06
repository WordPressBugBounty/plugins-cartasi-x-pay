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

class WC_Gateway_Nexi_Register_Available
{

    private static $xpayAllowedMethodsByCurrency = [
        'EUR' => [
            'PAYPAL',
            'SOFORT',
            'AMAZONPAY',
            'GOOGLEPAY',
            'APPLEPAY',
            'ALIPAY',
            'WECHATPAY',
            'GIROPAY',
            'IDEAL',
            'BCMC',
            'EPS',
            'P24',
            'BANCOMATPAY',
            'SCT',
            'SKRILL',
            'SKRILL1TAP',
            'MULTIBANCO',
            'SATISPAY',
            'PAGOINCONTO',
            'PAYBYBANK',
            'MY_BANK',
            'PAGODIL',
            'KLARNA',
            'PAGOLIGHT',
            'PAYPAL_BNPL',
        ],
        'CZK' => [
            'PAYU',
        ],
        'PLN' => [
            'PAYU',
            'BLIK',
        ],
        'NZD' => [
            'POLI',
        ],
        'AUD' => [
            'POLI',
        ],
        'GBP' => [
            'KLARNA',
        ],
        'DKK' => [
            'KLARNA',
        ],
    ];

    public static function register($paymentGateways)
    {
        $nexiGatewaysHelper = new static();

        return array_merge($paymentGateways, $nexiGatewaysHelper->get_all_nexi_gateways());
    }

    public static function registerBlocks()
    {
        $nexiGatewaysHelper = new static();

        return $nexiGatewaysHelper->paymentGatewaysBlocks;
    }

    private $paymentGateways;
    private $paymentGatewaysBlocks;
    private $currency;

    private function get_all_nexi_gateways()
    {
        if (\Nexi\WC_Admin_Page::migrate_data()) {
            return $this->paymentGateways;
        }

        return [];
    }

    private function __construct()
    {
        $this->evaluate_all();
    }

    public static function filter_available_payment_gateways($paymentGateways)
    {
        $currentCurrency = get_woocommerce_currency();

        $currentConfig = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        $isXPayEnabled = \Nexi\WC_Nexi_Helper::nexi_array_key_exists_and_equals($currentConfig, 'enabled', 'yes');

        $isNpg = \Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG($currentConfig);

        foreach ($paymentGateways as $code => $paymentGateway) {
            if ($code === "xpay") {
                if ($isXPayEnabled) {
                    if ($isNpg) {
                        if (is_admin() || self::is_currency_valid_for_npg_apm($currentCurrency, 'CARDS')) {
                            continue;
                        }
                    } else {
                        if (is_admin() || $currentCurrency == 'EUR') {
                            continue;
                        }
                    }
                }

                unset($paymentGateways[$code]);
            } else if (strpos($code, "xpay") !== false) {
                if ($isXPayEnabled) {
                    $config = get_option('woocommerce_' . $code . '_settings');

                    if (\Nexi\WC_Nexi_Helper::nexi_array_key_exists_and_equals($config, 'enabled', 'yes')) {
                        if ($isNpg) {
                            if (self::isValidNpgApm($paymentGateway, $currentCurrency)) {
                                continue;
                            }
                        } else {
                            if (self::isValidXPayApm($paymentGateway, $currentCurrency)) {
                                continue;
                            }
                        }
                    }
                }

                unset($paymentGateways[$code]);
            }
        }

        return $paymentGateways;
    }

    private static function isValidXPayApm($paymentGateway, $currentCurrency)
    {
        // Test current currency
        if (!WC_Nexi_Helper::nexi_array_key_exists_and_in_array(self::$xpayAllowedMethodsByCurrency, $currentCurrency, $paymentGateway->selectedCard)) {
            return false;
        }

        if (!wooommerce_has_block_checkout()) {
            if (isset(WC()->cart)) {
                $apmInfo = self::get_xpay_apm_info($paymentGateway->selectedCard);

                if ($apmInfo === null) {
                    return false;
                }

                // Test for minimum amount. Each APM can have a minimum amount for payment processing
                if (isset($apmInfo['min_amount'])) {
                    $currentCartAmount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit(WC()->cart->total, $currentCurrency);

                    if ($currentCartAmount < $apmInfo['min_amount']) {
                        return false;
                    }
                }

                // Test for maximum amount. Each APM can have a maximum amount for payment processing
                if (isset($apmInfo['max_amount'])) {
                    $currentCartAmount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit(WC()->cart->total, $currentCurrency);

                    if ($currentCartAmount > $apmInfo['max_amount']) {
                        return false;
                    }
                }

                // Test for Pagodil configuration
                if ($paymentGateway->selectedCard == 'PAGODIL') {
                    $xpaySettings = \Nexi\WC_Pagodil_Widget::getXPaySettings();

                    $pagodilConfig = \Nexi\WC_Pagodil_Widget::getPagodilConfig();

                    if (!\Nexi\WC_Pagodil_Widget::isQuoteInstallable($xpaySettings, $pagodilConfig, WC()->cart)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private static function isValidNpgApm($paymentGateway, $currentCurrency)
    {
        if (!self::is_currency_valid_for_npg_apm($currentCurrency, $paymentGateway->selectedCard)) {
            return false;
        }

        if (!wooommerce_has_block_checkout()) {
            if (isset(WC()->cart)) {
                $apmInfo = self::get_npg_apm_info($paymentGateway->selectedCard);

                if ($apmInfo === null) {
                    return false;
                }

                // Test for minimum amount. Each APM can have a minimum amount for payment processing
                if (isset($apmInfo['min_amount'])) {
                    $currentCartAmount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit(WC()->cart->total, $currentCurrency);

                    if ($currentCartAmount < $apmInfo['min_amount']) {
                        return false;
                    }
                }

                // Test for maximum amount. Each APM can have a maximum amount for payment processing
                if (isset($apmInfo['max_amount'])) {
                    $currentCartAmount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit(WC()->cart->total, $currentCurrency);

                    if ($currentCartAmount > $apmInfo['max_amount']) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function evaluate_all()
    {
        $this->paymentGateways = [];
        $this->paymentGatewaysBlocks = [];

        $page = isset($_GET['page']) ? wc_clean(wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? wc_clean(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? wc_clean(wp_unslash($_GET['section'])) : '';

        $onPaymentDetailsPage = is_admin() && $page == 'wc-settings' && $tab == 'checkout' && strpos($section, "xpay") !== false;

        if ($onPaymentDetailsPage) {
            $this->paymentGateways[] = new \Nexi\WC_Gateway_Admin();
        } else {
            $currentConfig = \Nexi\WC_Nexi_Helper::get_nexi_settings();

            $isBuild = WC_Nexi_Helper::nexi_is_build($currentConfig);

            if (WC_Nexi_Helper::nexi_is_gateway_NPG($currentConfig)) {
                if ($isBuild) {
                    $this->paymentGateways[] = new \Nexi\WC_Gateway_NPG_Cards_Build();
                    $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_NPG_Cards_Build_Blocks_Support();
                } else {
                    $this->paymentGateways[] = new \Nexi\WC_Gateway_NPG_Cards_Redirect();
                    $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_NPG_Cards_Blocks_Support();
                }

                foreach (WC_Nexi_Helper::get_npg_available_methods() as $am) {
                    $this->evaluate_one_apm_npg($currentConfig, $am);
                }
            } else {
                if ($isBuild) {
                    $this->paymentGateways[] = new \Nexi\WC_Gateway_XPay_Cards_Build();
                    $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_XPay_Cards_Build_Blocks_Support();
                } else {
                    $this->paymentGateways[] = new \Nexi\WC_Gateway_XPay_Cards_Redirect();
                    $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_XPay_Cards_Blocks_Support();
                }

                foreach (WC_Nexi_Helper::get_xpay_available_methods() as $am) {
                    $this->evaluate_one_apm_xpay($currentConfig, $am);
                }
            }
        }
    }

    private function evaluate_one_apm_xpay($currentConfig, $am)
    {
        if ($am['type'] != 'APM' || $am['selectedcard'] == '') {
            return;
        }

        $apmInfo = self::get_xpay_apm_info($am['selectedcard']);

        if ($apmInfo === null) {
            return;
        }

        if ($am['selectedcard'] == 'GOOGLEPAY' && WC_Nexi_Helper::is_google_button_enabled($currentConfig)) {
            $this->paymentGateways[] = new \Nexi\WC_Gateway_XPay_Google_Pay_Button(
                $apmInfo['title'],
                $apmInfo['description'],
                $am['pngImage']
            );

            $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_XPay_Google_Pay_Button_Blocks_Support(
                $apmInfo['title'],
                $apmInfo['description'],
                $am['pngImage']
            );
        } else if ($am['selectedcard'] == 'APPLEPAY' && WC_Nexi_Helper::is_apple_button_enabled($currentConfig)) {
            $this->paymentGateways[] = new \Nexi\WC_Gateway_XPay_Apple_Pay_Button(
                $apmInfo['title'],
                $apmInfo['description'],
                $am['pngImage']
            );

            $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_XPay_Apple_Pay_Button_Blocks_Support(
                $apmInfo['title'],
                $apmInfo['description'],
                $am['pngImage']
            );
        } else {
            $this->paymentGateways[] = new \Nexi\WC_Gateway_XPay_APM(
                $am['selectedcard'],
                $apmInfo['title'],
                $apmInfo['description'],
                $am['selectedcard'],
                $am['pngImage']
            );

            $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_XPay_APM_Blocks_Support(
                $am['selectedcard'],
                $apmInfo['title'],
                $apmInfo['description'],
                $am['pngImage']
            );
        }
    }

    private function evaluate_one_apm_npg($currentConfig, $am)
    {
        if ($am['paymentMethodType'] != 'APM') {
            return;
        }

        $apmInfo = self::get_npg_apm_info($am['circuit']);

        if ($apmInfo === null) {
            return;
        }

        if ($am['circuit'] == 'GOOGLEPAY' && WC_Nexi_Helper::is_google_button_enabled($currentConfig)) {
            $this->paymentGateways[] = new \Nexi\WC_Gateway_NPG_Google_Pay_Button(
                $apmInfo['title'],
                $apmInfo['description'],
                $am['imageLink']
            );

            $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_NPG_Google_Pay_Button_Blocks_Support(
                $apmInfo['title'],
                $apmInfo['description'],
                $am['imageLink']
            );
        } else {
            $this->paymentGateways[] = new \Nexi\WC_Gateway_NPG_APM(
                $am['circuit'],
                $apmInfo['title'],
                $apmInfo['description'],
                $am['circuit'],
                $am['imageLink']
            );

            $this->paymentGatewaysBlocks[] = new \Nexi\BlockSupport\WC_Gateway_NPG_APM_Blocks_Support(
                $am['circuit'],
                $apmInfo['title'],
                $apmInfo['description'],
                $am['imageLink']
            );
        }
    }

    public static function get_all_available_apm_info()
    {
        return [
            [
                'title' => 'PagoinConto',
                'description' => __('Simply pay by bank transfer directly from your home banking with PagoinConto', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'PAGOINCONTO',
                'selected_card_npg' => 'PAGOINCONTO',
            ],
            [
                'title' => 'PayByBank',
                'description' => __('Simply pay by bank transfer directly from your home banking with PayByBank', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'PAYBYBANK',
                'selected_card_npg' => 'PAYBYBANK',
            ],
            [
                'title' => 'Google Pay',
                'description' => __('Easily pay with your Google Pay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'GOOGLEPAY',
                'selected_card_npg' => 'GOOGLEPAY',
            ],
            [
                'title' => 'Apple Pay',
                'description' => __('Easily pay with your Apple Pay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'APPLEPAY',
                'selected_card_npg' => 'APPLEPAY',
            ],
            [
                'title' => 'Bancomat Pay',
                'description' => __('Pay via BANCOMAT Pay just by entering your phone number', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'BANCOMAT',
                'selected_card_npg' => 'BANCOMATPAY',
            ],
            [
                'title' => 'MyBank',
                'description' => __('Pay securely by bank transfer with MyBank', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'MY_BANK',
                'selected_card_npg' => 'MYBANK',
            ],
            [
                'title' => 'Alipay',
                'description' => __('Pay quickly and easily with your AliPay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'ALIPAY',
                'selected_card_npg' => 'ALIPAY',
            ],
            [
                'title' => 'WeChat Pay',
                'description' => __('Pay quickly and easily with your WeChat Pay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'WECHATPAY',
                'selected_card_npg' => 'WECHATPAY',
            ],
            [
                'title' => 'Giropay',
                'description' => __('Pay directly from your bank account with Giropay', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 10,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'GIROPAY',
                'selected_card_npg' => 'GIROPAY',
            ],
            [
                'title' => 'iDEAL',
                'description' => __('Pay directly from your bank account with iDEAL', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 10,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'IDEAL',
                'selected_card_npg' => 'IDEAL',
            ],
            [
                'title' => 'Bancontact',
                'description' => __('Pay easily with Bancontact', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'BCMC',
                'selected_card_npg' => 'BANCONTACT',
            ],
            [
                'title' => 'EPS',
                'description' => __('Real time payment directly from your bank account with EPS', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 100,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'EPS',
                'selected_card_npg' => 'EPS',
            ],
            [
                'title' => 'Przelewy24',
                'description' => __('Secure payment directly from your bank account with Przelewy24', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'P24',
                'selected_card_npg' => 'PRZELEWY24',
            ],
            [
                'title' => 'Skrill',
                'description' => __('Pay quickly and easily with your Skrill wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'SKRILL',
                'selected_card_npg' => 'SKRILL',
            ],
            [
                'title' => 'Skrill 1tap',
                'description' => __('Pay in one tap with your Skrill wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'SKRILLONETAP',
                'selected_card_npg' => 'SKRILL1TAP',
            ],
            [
                'title' => 'PayU',
                'description' => __('Secure payment directly from your bank account with PayU', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 300,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'PAYU',
                'selected_card_npg' => 'PAYU',
            ],
            [
                'title' => 'Blik',
                'description' => __('Secure payment directly from your home banking with Blik', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 100,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'BLIK',
                'selected_card_npg' => 'BLIK',
            ],
            [
                'title' => 'Multibanco',
                'description' => __('Secure payment directly from your home banking with Multibanco', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'MULTIBANCO',
                'selected_card_npg' => 'MULTIBANCO',
            ],
            [
                'title' => 'Satispay',
                'description' => __('Pay easily with your Satispay account', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'SATISPAY',
                'selected_card_npg' => 'SATISPAY',
            ],
            [
                'title' => 'Amazon Pay',
                'description' => __('Pay easily with your Amazon account', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'AMAZONPAY',
                'selected_card_npg' => 'AMAZONPAY',
            ],
            [
                'title' => 'PayPal',
                'description' => __('Pay securely with your PayPal account', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'PAYPAL',
                'selected_card_npg' => 'PAYPAL',
            ],
            [
                'title' => 'Oney',
                'description' => __('Pay in 3 or 4 installments by credit, debit or Postepay card with Oney', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => null,
                'selected_card_npg' => 'ONEY',
            ],
            [
                'title' => 'Klarna',
                'description' => __('Pay in 3 installments with Klarna interest-free', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 3500,
                'max_amount' => 150000,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'SOFORT',
                'selected_card_npg' => 'KLARNA',
            ],
            [
                'title' => 'PagoDil',
                'description' => __('Buy now and pay a little by little with PagoDIL', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'PAGODIL',
                'selected_card_npg' => 'PAGODIL',
            ],
            [
                'title' => 'HeyLight',
                'description' => __('Pay in installments with HeyLight', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 6000,
                'max_amount' => 500000,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'PAGOLIGHT',
                'selected_card_npg' => 'HEYLIGHT',
            ],
            [
                'title' => 'PayPal BNPL',
                'description' => __('Pay in 3 installments with PayPal', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 3000,
                'max_amount' => 200000,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'PAYPAL_BNPL',
                'selected_card_npg' => 'PAYPAL_BNPL',
            ],
            [
                'title' => 'POLi',
                'description' => __('Pay securely with POLi', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'POLI',
                'selected_card_npg' => null,
            ],
            [
                'title' => 'MyBank',
                'description' => __('Pay securely by bank transfer with MyBank', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'SCT',
                'selected_card_npg' => null,
            ],
            [
                'title' => 'Klarna',
                'description' => __('Pay in 3 installments with Klarna interest-free', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => 'KLARNA',
                'selected_card_npg' => null,
            ],
            [
                'title' => 'IRIS',
                'description' => __('Pay securely with IRIS', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
                'recurring_npg' => false,
                'recurring_xpay' => false,
                'selected_card_xpay' => null,
                'selected_card_npg' => 'IRIS',
            ],
        ];
    }

    public static function is_npg_recurring($apmCode)
    {
        $apmInfo = self::get_npg_apm_info($apmCode);

        if (isset($apmInfo)) {
            return $apmInfo['recurring_npg'];
        } else {
            return false;
        }
    }

    public static function get_npg_min_amount($apmCode)
    {
        $apmInfo = self::get_npg_apm_info($apmCode);

        if (isset($apmInfo)) {
            return $apmInfo['min_amount'];
        } else {
            return null;
        }
    }

    public static function get_npg_max_amount($apmCode)
    {
        $apmInfo = self::get_npg_apm_info($apmCode);

        if (isset($apmInfo)) {
            return $apmInfo['max_amount'];
        } else {
            return null;
        }
    }

    private static function get_npg_apm_info($apmCode)
    {
        if (isset($apmCode)) {
            $allApms = self::get_all_available_apm_info();

            foreach ($allApms as $apm) {
                if (strtolower(trim($apm['selected_card_npg'] ?? "")) === strtolower(trim($apmCode ?? ""))) {
                    return $apm;
                }
            }
        }

        return null;
    }

    public static function is_xpay_recurring($apmCode)
    {
        $apmInfo = self::get_xpay_apm_info($apmCode);

        if (isset($apmInfo)) {
            return $apmInfo['recurring_xpay'];
        } else {
            return false;
        }
    }

    public static function get_xpay_min_amount($apmCode)
    {
        $apmInfo = self::get_xpay_apm_info($apmCode);

        if (isset($apmInfo)) {
            return $apmInfo['min_amount'];
        } else {
            return null;
        }
    }

    public static function get_xpay_max_amount($apmCode)
    {
        $apmInfo = self::get_xpay_apm_info($apmCode);

        if (isset($apmInfo)) {
            return $apmInfo['max_amount'];
        } else {
            return null;
        }
    }

    private static function get_xpay_apm_info($apmCode)
    {
        if (isset($apmCode)) {
            $allApms = self::get_all_available_apm_info();

            foreach ($allApms as $apm) {
                if (strtolower(trim($apm['selected_card_xpay'] ?? "")) === strtolower(trim($apmCode ?? ""))) {
                    return $apm;
                }
            }
        }

        return null;
    }

    private static function is_currency_valid_for_npg_apm($currency, $apmCode)
    {
        $validApmCodes = array(
            "CARDS",
            "GOOGLEPAY",
            "APPLEPAY",
        );

        if (WC_Nexi_Helper::nexi_array_key_exists_and_equals(WC_Nexi_Helper::get_nexi_settings(), 'nexi_xpay_multicurrency_enabled', 'yes') && in_array($apmCode, $validApmCodes)) {
            return in_array($currency, \Nexi\WC_Gateway_NPG_Currency::get_npg_supported_currency_list());
        }

        return $currency == 'EUR';
    }

}
