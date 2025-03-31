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

class WC_Gateway_XPay_Cards_Blocks_Support extends WC_Gateway_Xpay_Blocks_Support
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
        $available_methods_xpay = json_decode(\WC_Admin_Settings::get_option('xpay_available_methods'), true);

        $contentIcons = [];

        if (is_array($available_methods_xpay)) {
            foreach ($available_methods_xpay as $am) {
                if ($am['type'] != "CC") {
                    continue;
                }
                $imageLink = $am['image'] ?? '';
                if (!empty($imageLink)) {
                    $contentIcons[$am['code'] . '-nexipay'] = [
                        'src' => $imageLink,
                        'alt' => __($am['description'] . " logo", WC_LANG_KEY)
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
