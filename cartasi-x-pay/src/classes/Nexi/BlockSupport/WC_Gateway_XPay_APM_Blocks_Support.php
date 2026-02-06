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

class WC_Gateway_XPay_APM_Blocks_Support extends WC_Gateway_XPay_Blocks_Support
{

    private $apmCode;
    private $title;
    private $description;
    private $image;

    public function __construct($apmCode, $title, $description, $image)
    {
        parent::__construct($apmCode);

        $this->apmCode = $apmCode;
        $this->title = $title;
        $this->description = $description;
        $this->image = $image;
    }

    protected function getLabel()
    {
        return $this->title;
    }

    protected function getContent()
    {
        return $this->description;
    }

    protected function getIcons()
    {
        return [
            "{$this->apmCode}-nexipay" => [
                'src' => $this->image,
                'alt' => "{$this->title} logo",
            ]
        ];
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

            if (!empty($installmentsNumber) && WC() !== null && WC()->cart !== null) {
                $firstInstallmentArray[] = $installmentsNumber[array_key_first($installmentsNumber)];
                $enabled = true;
                $firstInstallmentAmount = \Nexi\WC_Pagodil_Widget::calcInstallmentsAmount(\Nexi\WC_Nexi_Helper::mul_bcmul(WC()->cart->total, 100, 1), end($firstInstallmentArray));
            }

            return [
                'enabled' => $enabled,
                'options' => $installmentsNumberValues,
                'title_text' => __('Choose the number of installments', 'woocommerce-gateway-nexi-xpay'),
                'default_option' => $firstInstallmentArray[0] ?? '',
                'is_pago_dil' => true,
                'pago_dil_installment_amount_label' => sprintf(__('Amount: %s installments of %s€', 'woocommerce-gateway-nexi-xpay'), end($firstInstallmentArray), $firstInstallmentAmount),
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

    protected function get_min_amount()
    {
        return \Nexi\WC_Gateway_Nexi_Register_Available::get_xpay_min_amount($this->apmCode);
    }

    protected function get_max_amount()
    {
        return \Nexi\WC_Gateway_Nexi_Register_Available::get_xpay_max_amount($this->apmCode);
    }

    protected function getRecurringInfo()
    {
        return [
            'enabled' => \Nexi\WC_Gateway_Nexi_Register_Available::is_xpay_recurring($this->apmCode) && \Nexi\WC_Nexi_Helper::cart_contains_subscription(),
            'disclaimer_text' => __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay'),
        ];
    }

}
