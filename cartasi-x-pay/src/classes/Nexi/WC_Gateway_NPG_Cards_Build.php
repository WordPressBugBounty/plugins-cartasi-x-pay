<?php

/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     7.0.3
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */

namespace Nexi;

class WC_Gateway_NPG_Cards_Build extends WC_Gateway_NPG_Generic_Method
{

    public function __construct()
    {
        parent::__construct('xpay_build', true);

        $this->supports = array_merge($this->supports, ['tokenization']);

        $this->method_title = __('Payment cards', 'woocommerce-gateway-nexi-xpay');
        $this->method_description = __('Payment gateway.', 'woocommerce-gateway-nexi-xpay');
        $this->title = $this->method_title;

        $this->description = $this->get_sorted_cards_images() . __("Pay securely by credit, debit and prepaid card. Powered by Nexi.", 'woocommerce-gateway-nexi-xpay');

        $this->has_fields = true;

        add_filter('woocommerce_saved_payment_methods_list', [$this, 'filter_saved_payment_methods_list'], 10, 2);
    }

    public function filter_saved_payment_methods_list($list, $customer_id)
    {
        return [];
    }

    public function payment_fields()
    {
        global $wp;

        if (is_add_payment_method_page() && isset($wp->query_vars['add-payment-method'])) {
            echo __('New payment methods can only be added during checkout', 'woocommerce-gateway-nexi-xpay');
            return;
        }

        $isRecurring = WC_Nexi_Helper::cart_contains_subscription();

        echo $this->description . "<br>";

        if ($isRecurring) {
            echo "<br>" . __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay') . "<br>";
        }

        include_once WC_Nexi_Helper::get_nexi_template_path('npg_build_payment.php');
    }

    public function process_payment($order_id)
    {
        $orderId = $_REQUEST["orderId"];
        if (isset($_POST['npg_order_id'])) {
            $orderId = $_POST['npg_order_id'];
        }

        try {
            if (!(\Nexi\OrderHelper::getOrderMeta($orderId, '_npg_orderId', true) && \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_is_build', true))) {
                throw new \Exception('Order id not found or not a build order: ' . $orderId);
            }

            \Nexi\OrderHelper::updateOrderMeta($orderId, "_npg_" . "wc_order_id", $order_id);

            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_" . "is_build", true);
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_" . "orderId", \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_orderId', true));
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_" . "securityToken", \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_securityToken', true));
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_" . "sessionId", \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_sessionId', true));

            if (\Nexi\OrderHelper::getOrderMeta($orderId, '_npg_recurringContractId', true)) {
                \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_" . "recurringContractId", \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_recurringContractId', true));
            }

            $res = WC_Gateway_NPG_API::getInstance()->build_payment_finalize(\Nexi\OrderHelper::getOrderMeta($order_id, '_npg_sessionId', true));

            if (!in_array($res['state'], ['REDIRECTED_TO_EXTERNAL_DOMAIN', 'PAYMENT_COMPLETE'])) {
                throw new \Exception('Invalid state returned from payment finalize: ' . json_encode($res));
            }

            if ($res['state'] === "REDIRECTED_TO_EXTERNAL_DOMAIN") {
                $result = 'success';
                $redirectLink = $res['url'];
            } else {
                if (isset($res['operation']) && !empty($res['operation'])) {
                    \Nexi\WC_Gateway_NPG_Process_Completion::change_order_status_by_operation($order_id, $res['operation']);

                    $order = new \WC_Order($order_id);

                    if (in_array($order->get_status(), ['failed', 'cancelled'])) {
                        $result = 'failure';
                        $redirectLink = $this->get_return_url($order);
                    } else {
                        $result = 'success';
                        $redirectLink = $this->get_return_url($order);
                    }
                } else {
                    throw new \Exception('Operation not set on finalize response: ' . json_encode($res));
                }
            }
        } catch (\Throwable $th) {
            Log::actionWarning(__FUNCTION__ . ': ' . $th->getMessage());

            wc_add_notice(__("Error during payment proccess", "woocommerce-gateway-nexi-xpay"), "error");

            $order = new \WC_Order($order_id);

            $result = 'failure';
            $redirectLink = $this->get_return_url($order);
        }

        $resultArray = [
            'result' => $result,
            'redirect' => $redirectLink,
        ];

        return $resultArray;
    }

    public static function get_build_fields()
    {
        try {
            $total = floatval(WC()->cart->total);

            $isRecurring = WC_Nexi_Helper::cart_contains_subscription();

            $orderId = null;

            if (strlen(trim($_POST['orderId'])) > 0) {
                $orderId = $_POST['orderId'];
            }

            $buildParams = WC_Gateway_NPG_API::getInstance()->build_payment($total, WC(), $isRecurring, $orderId);

            unset($buildParams['securityToken']);
            unset($buildParams['sessionId']);

            wp_send_json([
                'orderId' => $buildParams["orderId"],
                'fields' => $buildParams['fields'],
            ]);
        } catch (\Exception $exc) {
            wp_send_json([
                'error_msg' => $exc->getMessage()
            ]);
        }

        wp_die();
    }

}
