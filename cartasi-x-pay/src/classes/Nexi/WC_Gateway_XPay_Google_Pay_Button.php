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

class WC_Gateway_XPay_Google_Pay_Button extends WC_Gateway_XPay_Generic_Method
{

    public $selectedCard = "GOOGLEPAY";

    public function __construct($title, $description, $img)
    {
        parent::__construct('xpay_googlepay_button', false);

        $this->method_title = $title;
        $this->method_description = $description;
        $this->title = $this->method_title;
        $this->icon = $img;
        $this->description = $this->method_description;

        add_action('woocommerce_receipt_' . $this->id, array($this, 'exec_payment'));

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $title = __("APMs do not have a custom configuration. ", 'woocommerce-gateway-nexi-xpay');
        $title .= " ";
        $title .= __("Please use ", 'woocommerce-gateway-nexi-xpay');
        $title .= __('Nexi XPay', 'woocommerce-gateway-nexi-xpay');
        $title .= __(" configurations", 'woocommerce-gateway-nexi-xpay');

        $this->form_fields = array(
            'title_section_1' => array(
                'title' => $title,
                'type' => 'title',
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __("Enable Nexi XPay payment plugin.", 'woocommerce-gateway-nexi-xpay'),
                'default' => 'yes'
            ),
        );
    }

    public function payment_fields()
    {
        echo $this->description;

        ?>
        <script>
            jQuery(document).ready(function () {
                if (jQuery('input[name="payment_method"]:checked').val() === "xpay_googlepay_button") {
                    jQuery("#place_order").hide();
                }
            });
        </script>

        <input type="hidden" id="xpay_admin_url" value="<?php echo admin_url() ?>" />

        <input type="hidden" id="googlePayJson" name="google_pay_json" />

        <fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form" class="wc-credit-card-form wc-payment-form">
            <div id="googlepay-button-container"></div>
        </fieldset>
        <?php
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);

        try {
            $googlePayJson = filter_input(INPUT_POST, 'google_pay_json') ?? $_POST['google_pay_json'];
            $codTrans = substr("GP-" . date('ysdim') . "-" . time(), 0, 30);
            $divisa = get_woocommerce_currency();
            $amount = WC_Nexi_Helper::mul_bcmul($order->get_total(), 100, 0);
            $url = get_rest_url(null, "woocommerce-gateway-nexi-xpay/xpay/gpay/result/" . $order->get_id());

            $response = \Nexi\WC_Gateway_XPay_API::getInstance()->googlePayPayment($codTrans, $amount, $divisa, $googlePayJson, $url, $order);

            \Nexi\OrderHelper::updateOrderMeta($order_id, "xpay_transaction_id", $codTrans);
            \Nexi\OrderHelper::updateOrderMeta($order_id, "xpay_divisa", $divisa);

            if (isset($response['html'])) {
                \Nexi\OrderHelper::updateOrderMeta($order_id, "gpay_html", $response['html']);
            } else {
                $order->add_order_note(__("Nexi XPay payment successful", 'woocommerce-gateway-nexi-xpay'));

                $order->payment_complete();

                WC()->cart->empty_cart();

                WC_Save_Order_Meta::saveSuccessXPay(
                    $order_id,
                    \Nexi\WC_Gateway_XPay_API::getInstance()->get_build_alias(),
                    null,
                    $codTrans,
                    null
                );
            }

            $result = 'success';
            $redirect = get_rest_url(null, "woocommerce-gateway-nexi-xpay/gpay/redirect/" . $order->get_id());
        } catch (\Throwable $th) {
            Log::actionWarning(__FUNCTION__ . ": error: " . $th->getMessage());

            \Nexi\OrderHelper::updateOrderMeta($order_id, '_xpay_last_error', $th->getMessage());

            $order->update_status('failed');

            $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $th->getMessage());

            if ($th->getCode() == 96) {
                wc_add_notice(__("Payment denied, please retry the transaction using the same card.", 'woocommerce-gateway-nexi-xpay'), "error");
            } else {
                wc_add_notice(__("Thank you for shopping with us. However, the transaction has been declined.", 'woocommerce-gateway-nexi-xpay') . " - " . ($th->getMessage()), "error");
            }

            $result = 'failure';
            $redirect = $this->get_return_url($order);
        }

        $resultArray = [
            'result' => $result,
            'redirect' => $redirect,
        ];

        return $resultArray;
    }

}
