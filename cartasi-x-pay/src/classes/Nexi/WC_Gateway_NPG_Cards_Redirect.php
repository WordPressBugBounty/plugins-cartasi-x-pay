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

class WC_Gateway_NPG_Cards_Redirect extends WC_Gateway_NPG_Cards
{

    public function filter_saved_payment_methods_list($list, $customer_id)
    {
        $gatewaySettings = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        if (empty($gatewaySettings) || ($gatewaySettings['nexi_xpay_oneclick_enabled'] ?? '') !== 'yes' || \Nexi\WC_Nexi_Helper::cart_contains_subscription()) {
            return [];
        }

        return $list;
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);
        $result = 'failure';

        try {
            $recurringPayment = WC_Nexi_Helper::order_or_cart_contains_subscription($order);

            $selectedToken = 'new';

            if (isset($_REQUEST["wc-" . $this->id . "-payment-token"])) {
                $selectedToken = $_REQUEST["wc-" . $this->id . "-payment-token"];
            } else if (isset($_POST["wc-" . $this->id . "-payment-token"])) {
                $selectedToken = $_POST["wc-" . $this->id . "-payment-token"];
            }

            $saveCard = false;
            if (isset($_REQUEST["save-card-npg"])) {
                $saveCard = $_REQUEST["save-card-npg"] == "1";
            } else if (isset($_POST['wc-' . $this->id . '-new-payment-method'])) {
                $saveCard = $_POST['wc-' . $this->id . '-new-payment-method'] == "1";
            }

            $installmentsNumber = 0;

            if (isset($_REQUEST["nexi-xpay-installments-number"])) {
                $installmentsNumber = $_REQUEST["nexi-xpay-installments-number"];
            } else if (isset($_POST['nexi_xpay_number_of_installments'])) {
                $installmentsNumber = $_POST['nexi_xpay_number_of_installments'];
            }

            $redirectLink = WC_Gateway_NPG_API::getInstance()->new_payment_link($order, $recurringPayment, $selectedToken, $saveCard, 'CARDS', $installmentsNumber);

            $result = 'success';
        } catch (\Throwable $th) {
            wc_add_notice($th->getMessage(), "error");

            $redirectLink = $this->get_return_url($order);
        }

        $resultArray = [
            'result' => $result,
            'redirect' => $redirectLink,
        ];

        return $resultArray;
    }

    public function payment_fields()
    {
        global $wp;

        if (is_add_payment_method_page() && isset($wp->query_vars['add-payment-method'])) {
            echo '<b>' . __('New payment methods can only be added during checkout.', 'woocommerce-gateway-nexi-xpay') . '</b>';
            return;
        }

        $this->tokenization_script();

        echo "<p>" . $this->description . '<br /></p>';

        echo $this->get_npg_cards_icon();

        $isRecurring = WC_Nexi_Helper::cart_contains_subscription();

        $installmentsInfo = self::get_installments_info();

        if ($isRecurring) {
            ?>
            <fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form">
                <?php
                echo __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay');
                ?>
            </fieldset>
            <?php
        } else {
            if ($this->settings["nexi_xpay_oneclick_enabled"] == "yes") {
                $this->saved_payment_methods();

                ?>
                <fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form">
                    <p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                        <input id="save-card-npg" name="save-card-npg" type="checkbox" value="1" style="width:auto;" />
                        <label for="save-card-npg"
                            style="display:inline;"><?php echo __('Remember the payment option.', 'woocommerce-gateway-nexi-xpay'); ?></label>
                    </p>
                </fieldset>
                <?php
            }

            if ($installmentsInfo["installments_enabled"]) {
                ?>
                <fieldset>
                    <label for="nexi-xpay-installments-number" style="display: block;">
                        <?php echo __('Installments', 'woocommerce-gateway-nexi-xpay'); ?>
                    </label>
                    <select id="nexi-xpay-installments-number" name="nexi-xpay-installments-number">
                        <option value=""><?php echo __('One time solution', 'woocommerce-gateway-nexi-xpay'); ?></option>
                        <?php foreach ($installmentsInfo['max_installments'] as $installmentsNumber) { ?>
                            <option value="<?php echo $installmentsNumber; ?>"><?php echo $installmentsNumber; ?></option>
                        <?php } ?>
                    </select>
                </fieldset>
                <?php
            }
        }
    }

    public static function woocommerce_payment_token_deleted($token_id, $token)
    {
        if (\Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
            if ($token->get_gateway_id() === GATEWAY_XPAY) {
                \Nexi\WC_Gateway_NPG_API::getInstance()->deactivate_contract($token->get_token());
            }
        }
    }

    public static function get_installments_info()
    {
        $gatewaySettings = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        $installmentsEnabled = $gatewaySettings["nexi_xpay_installments_enabled"] === "yes";

        $maxInstallments = array();

        if ($installmentsEnabled) {
            $tot = min($gatewaySettings["nexi_xpay_max_installments"] ?? 99, self::get_max_installments_number_by_cart());

            for ($i = 2; $i <= $tot; $i++) {
                $maxInstallments[] = $i;
            }
        }

        return array(
            'installments_enabled' => $installmentsEnabled && count($maxInstallments) > 0,
            'max_installments' => $maxInstallments,
        );
    }

    private static function get_max_installments_number_by_cart()
    {
        $gatewaySettings = \Nexi\WC_Nexi_Helper::get_nexi_settings();

        $nInstallments = null;

        $ranges = json_decode($gatewaySettings["nexi_xpay_installments_ranges"], true);

        if (is_array($ranges) && count($ranges)) {
            $baseGrandTotal = floatval(WC()->cart->total);

            $rangesValues = array_values($ranges);

            $toAmount = array_column($rangesValues, 'to_amount');

            array_multisort($toAmount, SORT_ASC, $rangesValues);

            foreach ($rangesValues as $value) {
                if ($baseGrandTotal <= $value['to_amount']) {
                    $nInstallments = (int) $value['n_installments'];
                    break;
                }
            }
        }

        return $nInstallments ?? 99;
    }

}
