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

class WC_Gateway_NPG_Cards_Build extends WC_Gateway_NPG_Cards
{

    public function __construct()
    {
        parent::__construct();

        $this->has_fields = true;

        wp_enqueue_script('xpay_npg_build_lib', \Nexi\WC_Gateway_NPG_API::getInstance()->getUrlNpgBuildJS());
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

        echo "<p>" . $this->description . "<br /></p>";

        if ($isRecurring) {
            echo "<p><br />" . __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay') . "<br /></p>";
        }

        echo $this->get_npg_cards_icon();

        include_once WC_Nexi_Helper::get_nexi_template_path('npg_build_payment.php');
    }

    public function process_payment($order_id)
    {
        $orderId = WC()->session->get('npg_build_order_id');

        try {
            if (!\Nexi\OrderHelper::getOrderMeta($orderId, '_npg_orderId', true) || !\Nexi\OrderHelper::getOrderMeta($orderId, '_npg_is_build', true)) {
                throw new \Exception('Order id not found or not a build order: ' . $orderId);
            }

            $sessionId = \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_sessionId', true);

            \Nexi\OrderHelper::updateOrderMeta($orderId, "_npg_wc_order_id", $order_id);

            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_is_build", true);
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_orderId", \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_orderId', true));
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_securityToken", \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_securityToken', true));
            \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_sessionId", $sessionId);

            if (\Nexi\OrderHelper::getOrderMeta($orderId, '_npg_recurringContractId', true)) {
                \Nexi\OrderHelper::updateOrderMeta($order_id, "_npg_recurringContractId", \Nexi\OrderHelper::getOrderMeta($orderId, '_npg_recurringContractId', true));
            }

            $buildState = WC_Gateway_NPG_API::getInstance()->build_state($sessionId);

            if ($buildState['state'] == 'PAYMENT_COMPLETE') {
                [$result, $redirectLink] = $this->check_npg_operation($order_id, $buildState['operation']);
            } else {
                $res = WC_Gateway_NPG_API::getInstance()->build_payment_finalize(WC(), $sessionId);

                if (!in_array($res['state'], ['REDIRECTED_TO_EXTERNAL_DOMAIN', 'PAYMENT_COMPLETE'])) {
                    throw new \Exception('Invalid state returned from payment finalize: ' . json_encode($res));
                }

                if ($res['state'] === "REDIRECTED_TO_EXTERNAL_DOMAIN") {
                    $result = 'success';
                    $redirectLink = $res['url'];
                } else {
                    [
                        $result,
                        $redirectLink,
                    ] = $this->check_npg_operation($order_id, $res['operation']);
                }

                if ($result == 'failure') {
                    WC()->session->__unset('npg_build_order_id');
                }
            }
        } catch (\Throwable $th) {
            Log::actionWarning(__FUNCTION__ . ': ' . $th->getMessage());

            wc_add_notice(__("Error during payment proccess", "woocommerce-gateway-nexi-xpay"), "error");

            $order = new \WC_Order($order_id);

            WC()->session->__unset('npg_build_order_id');

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

            $buildParams = WC_Gateway_NPG_API::getInstance()->build_payment($total, WC(), $isRecurring);

            unset($buildParams['securityToken']);
            unset($buildParams['sessionId']);

            wp_send_json([
                'fields' => $buildParams['fields'],
                'cssLink' => WC_GATEWAY_XPAY_PLUGIN_URL . '/assets/css/npg-build.css',
            ]);
        } catch (\Exception $exc) {
            wp_send_json([
                'error_msg' => $exc->getMessage()
            ]);
        }

        wp_die();
    }

}
