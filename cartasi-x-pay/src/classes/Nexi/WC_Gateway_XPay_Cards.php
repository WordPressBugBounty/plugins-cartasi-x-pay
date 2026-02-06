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

class WC_Gateway_XPay_Cards extends WC_Gateway_XPay_Generic_Method
{

    public function __construct()
    {
        parent::__construct('xpay', true);

        $this->supports = array_merge($this->supports, ['tokenization']);

        $this->method_title = __('Payment cards', 'woocommerce-gateway-nexi-xpay');
        $this->method_description = __('Pay securely by credit, debit and prepaid card. Powered by Nexi.', 'woocommerce-gateway-nexi-xpay');
        $this->title = $this->method_title;
        $this->description = $this->method_description;

        $this->selectedCard = "CC";

        if (\WC_Admin_Settings::get_option('xpay_logo_small') == "") {
            $this->icon = WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL;
        } else {
            $this->icon = \WC_Admin_Settings::get_option('xpay_logo_small');
        }

        add_action('woocommerce_receipt_' . $this->id, array($this, 'exec_payment'));

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));

        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);

        add_filter('woocommerce_saved_payment_methods_list', [$this, 'filter_saved_payment_methods_list'], 10, 2);
    }

    public function get_icon()
    {
        return '<div class="nexixpay-loghi-container"><div class="internal-container"><div class="img-container"><img class="nexi-card-image" src="' . WC_GATEWAY_XPAY_PLUGIN_URL . '/assets/images/card.png" alt="Payment cards" /></div></div></div>';
    }

}
