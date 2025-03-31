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

class WC_Gateway_NPG_Cards_Blocks_Support extends WC_Gateway_NPG_Blocks_Support
{
    public function __construct(
    ) {
        parent::__construct();
    }

    protected function getLabel()
    {
        return __('Payment cards', WC_LANG_KEY);
    }

    protected function getContent()
    {
        return __('Pay securely by credit, debit and prepaid card. Powered by Nexi.', WC_LANG_KEY);
    }

    protected function getIcons()
    {
        $available_methods_npg = json_decode(\WC_Admin_Settings::get_option('xpay_npg_available_methods'), true);

        $contentIcons = [];

        if (is_array($available_methods_npg)) {
            foreach ($available_methods_npg as $am) {
                if ($am['paymentMethodType'] != "CARDS") {
                    continue;
                }
                $imageLink = $am['imageLink'] ?? '';
                if (!empty($imageLink) && $imageLink !== 'no image') {
                    $contentIcons[$am['circuit'] . '-nexipay'] = [
                        'src' => $am['imageLink'],
                        'alt' => __($am['circuit'] . " logo", WC_LANG_KEY)
                    ];
                }
            }
        }

        return $contentIcons;
    }

    protected function getContentIcons()
    {
        return [];
    }

    protected function savedCardsSupport()
    {
        $gatewaySettings = \WC_Admin_Settings::get_option('woocommerce_xpay_settings') ?? [];
        if (empty($gatewaySettings) || ($gatewaySettings['nexi_xpay_oneclick_enabled'] ?? '') !== 'yes') {
            return false;
        }
        return true;
    }

    protected function getInstallmentsInfo()
    {
        $gw = new \Nexi\WC_Gateway_NPG_Cards();

        $installmentsInfo = $gw->get_installments_info();

        return [
            'enabled' => $installmentsInfo['installments_enabled'],
            'options' => $installmentsInfo['max_installments'],
            'title_text' => __('Installments', 'woocommerce-gateway-nexi-xpay'),
            'one_solution_text' => __('One time solution', 'woocommerce-gateway-nexi-xpay'),
        ];


    }

    protected function getRecurringInfo()
    {
        return [
            'enabled' => \Nexi\WC_Nexi_Helper::cart_contains_subscription(),
            'disclaimer_text' => __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay')
        ];
    }


}
