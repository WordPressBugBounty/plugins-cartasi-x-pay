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

namespace Nexi\BlockSupport;

class WC_Gateway_XPay_Cards_Blocks_Support extends WC_Gateway_XPay_Blocks_Support
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function getLabel()
    {
        return __('Payment cards', 'woocommerce-gateway-nexi-xpay');
    }

    protected function getContent()
    {
        return __('Pay securely by credit, debit and prepaid card. Powered by Nexi.', 'woocommerce-gateway-nexi-xpay');
    }

    protected function getAllCardsIcons()
    {
        $allCardsIcons = [];

        foreach (\Nexi\WC_Nexi_Helper::get_xpay_cards() as $am) {
            if ($am['pngImage']) {
                $allCardsIcons[$am['selectedcard'] . '-nexipay'] = [
                    'src' => $am['pngImage'],
                    'alt' => $am['description'],
                ];
            }
        }

        return $allCardsIcons;
    }

    protected function getIcons()
    {
        return [
            'xpay-nexixpay' => [
                'src' => WC_GATEWAY_XPAY_PLUGIN_URL . '/assets/images/card.png',
                'alt' => 'Payment cards',
            ]
        ];
    }

    protected function getContentIcons()
    {
        return [
            'xpay-nexipay' => [
                'src' => \WC_Admin_Settings::get_option('xpay_logo_small'),
                'alt' => __("Nexi XPay logo", 'woocommerce-gateway-nexi-xpay')
            ],
        ];
    }

    protected function savedCardsSupport()
    {
        return false;
    }

    protected function getRecurringInfo()
    {
        return [
            'enabled' => \Nexi\WC_Nexi_Helper::cart_contains_subscription(),
            'disclaimer_text' => __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay')
        ];
    }

}
