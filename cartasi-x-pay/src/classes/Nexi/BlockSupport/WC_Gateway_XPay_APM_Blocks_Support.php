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

class WC_Gateway_XPay_APM_Blocks_Support extends WC_Gateway_Xpay_Blocks_Support
{
    private $apmCode;
    private $label;


    public function __construct(
        $apmCode,
        $apmLabel,
        $isBuild = false,
    ) {
        parent::__construct($apmCode, $isBuild);
        $this->apmCode = $apmCode;
        $this->label = $apmLabel;
    }

    protected function getLabel()
    {
        if ($this->apmCode === 'PAGODIL') {
            return __("Pay in installments without interest", WC_LANG_KEY);
        }
        return $this->label;
    }

    protected function getContent()
    {
        if ($this->apmCode === 'PAGODIL') {
            return __('With PagoDIL by Cofidis, the merchant allows you to defer the payment in convenient installments without costs or interest.', WC_LANG_KEY);
        }
        return $this->label . __(' via Nexi XPay', WC_LANG_KEY);
    }

    protected function getIcons()
    {
        $available_methods_xpay = json_decode(\WC_Admin_Settings::get_option('xpay_available_methods'), true);

        $icons = [];

        if (is_array($available_methods_xpay)) {
            foreach ($available_methods_xpay as $am) {
                if ($am['code'] !== $this->apmCode) {
                    continue;
                }
                $imageLink = $am['image'] ?? '';
                if (!empty($imageLink)) {
                    $icons[$am['code'] . '-nexipay'] = [
                        'src' => $imageLink,
                        'alt' => __($am['description'] . " logo", WC_LANG_KEY)
                    ];
                }
            }
        }

        return $icons;
    }

    protected function getContentIcons()
    {
        return [];
    }

    protected function getInstallmentsInfo()
    {
        if ($this->apmCode === 'PAGODIL') {
            $installmentsNumber = \Nexi\WC_Pagodil_Widget::getAvailableInstallmentsNumber();

            $installmentsNumberValues = [];
            foreach ($installmentsNumber as $value) {
                $installmentsNumberValues[] = $value;
            }

            \Nexi\Log::actionDebug(json_encode($installmentsNumberValues));

            $firstInstallmentArray = [];
            $firstInstallmentAmount = '';

            $enabled = false;

            if (!empty($installmentsNumber)) {
                $firstInstallmentArray[] = $installmentsNumber[array_key_first($installmentsNumber)];
                $enabled = true;
                $firstInstallmentAmount = \Nexi\WC_Pagodil_Widget::calcInstallmentsAmount(\Nexi\WC_Nexi_Helper::mul_bcmul(WC()->cart->total, 100, 1), end($firstInstallmentArray));
            }

            return [
                'enabled' => $enabled,
                'options' => $installmentsNumberValues,
                'title_text' => __('Choose the number of installments', WC_LANG_KEY),
                'default_option' => $firstInstallmentArray[0] ?? '',
                'is_pago_dil' => true,
                'pago_dil_installment_amount_label' => sprintf(__('Amount: %s installments of %s€', WC_LANG_KEY), end($firstInstallmentArray), $firstInstallmentAmount),
                'pago_dil_admin_url' => admin_url(),
            ];
        }
        return [
            'enabled' => false,
            'options' => [],
            'title_text' => '',
            'one_solution_text' => '',
        ];
    }

    protected function is_active_method_specific()
    {
        if ($this->apmCode == 'PAGODIL' && !is_admin()) {
            if (isset(WC()->cart)) {
                $xpaySettings = \Nexi\WC_Pagodil_Widget::getXPaySettings();

                $pagodilConfig = \Nexi\WC_Pagodil_Widget::getPagodilConfig();

                if (!\Nexi\WC_Pagodil_Widget::isQuoteInstallable($xpaySettings, $pagodilConfig, WC()->cart)) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }


}
