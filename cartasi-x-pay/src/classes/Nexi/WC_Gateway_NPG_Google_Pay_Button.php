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

class WC_Gateway_NPG_Google_Pay_Button extends WC_Gateway_NPG_Generic_Method
{

    public $selectedCard = "GOOGLEPAY";

    public function __construct($title, $description, $img)
    {
        parent::__construct('xpay_npg_googlepay_button', false);

        $this->method_title = $title;
        $this->method_description = $description;
        $this->title = $this->method_title;
        $this->icon = $img;
        $this->description = $this->method_description;

        add_action('woocommerce_receipt_' . $this->id, [$this, 'exec_payment']);

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_save']);
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $title = __("APMs do not have a custom configuration. ", 'woocommerce-gateway-nexi-xpay');
        $title .= " ";
        $title .= __("Please use ", 'woocommerce-gateway-nexi-xpay');
        $title .= __('Nexi XPay', 'woocommerce-gateway-nexi-xpay');
        $title .= __(" configurations", 'woocommerce-gateway-nexi-xpay');

        $this->form_fields = [
            'title_section_1' => [
                'title' => $title,
                'type' => 'title',
            ],
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __("Enable Nexi XPay payment plugin.", 'woocommerce-gateway-nexi-xpay'),
                'default' => 'yes'
            ],
        ];
    }

    public function payment_fields()
    {
        echo $this->description;

        ?>
        <input type="hidden" id="xpay_admin_url" value="<?php echo admin_url() ?>" />

        <input type="hidden" id="googlePayJson" name="google_pay_json" />

        <fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form" class="wc-credit-card-form wc-payment-form">
            <div id="googlepay-button-container"></div>
        </fieldset>

        <script>
            jQuery(document).ready(function () {
                if (jQuery('input[name="payment_method"]:checked').val() === "xpay_npg_googlepay_button") {
                    jQuery("#place_order").hide();
                }
            });
        </script>
        <?php
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);

        try {
            $googlePayJson = filter_input(INPUT_POST, 'google_pay_json') ?? $_POST['google_pay_json'];

            $response = WC_Gateway_NPG_API::getInstance()->google_pay_payment($order, $googlePayJson);

            if ($response['paymentFlow'] == "CRYPTOGRAM") {
                [$result, $redirect] = $this->check_npg_operation($order_id, $response['operation']);
            } else {
                \Nexi\OrderHelper::updateOrderMeta($order_id, "googlepay_npg_response", json_encode($response));

                $result = 'success';
                $redirect = get_rest_url(null, "woocommerce-gateway-nexi-xpay/googlepay/panonly/" . $order->get_id());
            }
        } catch (\Throwable $th) {
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
