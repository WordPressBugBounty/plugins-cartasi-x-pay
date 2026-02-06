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

class WC_Gateway_XPay_Process_Completion
{

    public static function action_template_redirect()
    {
        global $wp_query;

        if (isset($wp_query->query_vars['woocommerce-gateway-nexi-xpay-redirect'])) {
            $safe_get_params = \Nexi\WC_Nexi_Helper::get_safe_get_params();

            $safe_get_params['id'] = get_query_var('nexi_order_id');

            self::redirect($safe_get_params);
        } else if (isset($wp_query->query_vars['woocommerce-gateway-nexi-xpay-cancel'])) {
            $safe_get_params = \Nexi\WC_Nexi_Helper::get_safe_get_params();

            $safe_get_params['id'] = get_query_var('nexi_order_id');

            self::cancel($safe_get_params);
        }
    }

    public static function rest_api_init()
    {
        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/s2s/xpay/(?P<id>\d+)',
            array(
                'methods' => 'POST',
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::s2s',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/process_account/xpay/(?P<id>\d+)',
            array(
                'methods' => array('POST'),
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::process_account',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => function () {
                    return current_user_can('manage_woocommerce');
                }
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/gpay/redirect/(?P<id>\d+)',
            array(
                'methods' => 'GET',
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::gpayRedirect',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/xpay/gpay/result/(?P<id>\d+)',
            array(
                'methods' => 'GET',
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::xpayGpayResult',
                'args' => [
                    'id' => [],
                ],
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function gpayRedirect($data)
    {
        $params = $data->get_params();

        $order_id = $params["id"];

        Log::actionInfo(__FUNCTION__ . ": for order id " . $order_id);

        $gpayHtml = \Nexi\OrderHelper::getOrderMeta($order_id, 'gpay_html', true);

        if ($gpayHtml) {
            header('Content-Type: text/html');
            echo $gpayHtml;
            exit;
        } else {
            $order = new \WC_Order($order_id);

            return new \WP_REST_Response(
                "redirecting success...",
                "303",
                array("Location" => $order->get_checkout_order_received_url())
            );
        }
    }

    public static function xpayGpayResult($data)
    {
        $params = $data->get_params();

        $order_id = $params["id"];

        $order = new \WC_Order($order_id);

        Log::actionInfo(__FUNCTION__ . ": for order id " . $order_id);

        if (\Nexi\WC_Gateway_XPay_API::getInstance()->validate_gpay_mac($_REQUEST)) {
            $nonce = $_REQUEST["xpayNonce"];
            $importo = WC_Nexi_Helper::mul_bcmul($order->get_total(), 100, 0);
            $codiceTransazione = \Nexi\OrderHelper::getOrderMeta($order_id, "xpay_transaction_id", true);
            $divisa = \Nexi\OrderHelper::getOrderMeta($order_id, "xpay_divisa", true);

            try {
                \Nexi\WC_Gateway_XPay_API::getInstance()->paga3DS($codiceTransazione, $importo, $nonce, $divisa, $order);

                if (!in_array($order->get_status(), ['completed', 'processing'])) {
                    WC_Save_Order_Meta::saveSuccessXPay(
                        $order_id,
                        \Nexi\WC_Gateway_XPay_API::getInstance()->get_build_alias(),
                        '',
                        $codiceTransazione,
                        ''
                    );

                    $order->payment_complete($codiceTransazione);
                }

                return new \WP_REST_Response(
                    "redirecting success...",
                    "303",
                    array("Location" => $order->get_checkout_order_received_url())
                );
            } catch (\Throwable $th) {
                Log::actionWarning(__FUNCTION__ . ": error: " . $th->getMessage());

                if (!in_array($order->get_status(), ['failed', 'cancelled'])) {
                    $order->update_status('failed');
                }

                \Nexi\OrderHelper::updateOrderMeta($order_id, '_xpay_last_error', $_REQUEST["messaggio"]);

                $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $_REQUEST["messaggio"]);
            }
        } else {
            if (!in_array($order->get_status(), ['failed', 'cancelled'])) {
                $order->update_status('failed');
            }

            \Nexi\OrderHelper::updateOrderMeta($order_id, '_xpay_last_error', $_REQUEST["messaggio"]);

            $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $_REQUEST["messaggio"]);
        }

        return new \WP_REST_Response(
            "redirecting failed...",
            "303",
            array("Location" => $order->get_cancel_order_url_raw())
        );
    }

    public static function s2s($data)
    {
        $params = $data->get_params();
        $order_id = $params["id"];

        Log::actionInfo(__FUNCTION__ . ": S2S notification for order id " . $order_id);

        $status = "500";
        $payload = array(
            "outcome" => "KO",
            "order_id" => $order_id,
        );

        try {
            if (\Nexi\WC_Gateway_XPay_API::getInstance()->validate_return_mac($_POST)) {
                $order = new \WC_Order($order_id);

                if ($_POST['esito'] == "OK") {
                    if (!in_array($order->get_status(), ['completed', 'processing'])) {
                        WC_Save_Order_Meta::saveSuccessXPay(
                            $order_id,
                            $_POST['alias'],
                            WC_Nexi_Helper::nexi_array_key_exists($_POST, 'num_contratto') ? $_POST['num_contratto'] : '',
                            $_POST['codTrans'],
                            WC_Nexi_Helper::nexi_array_key_exists($_POST, 'scadenza_pan') ? $_POST['scadenza_pan'] : ''
                        );

                        $completed = $order->payment_complete($_POST["codTrans"]);
                    }

                    if (!isset($completed) || $completed) {
                        $status = "200";
                        $payload = array(
                            "outcome" => "OK",
                            "order_id" => $order_id,
                        );
                    }
                } else if ($_POST['esito'] == "PEN") {
                    if ($order->get_status() != 'pd-pending-status') {
                        $order->update_status('pd-pending-status');
                    }

                    $status = "200";
                    $payload = array(
                        "outcome" => "OK",
                        "order_id" => $order_id,
                    );
                } else {
                    if (!in_array($order->get_status(), ['failed', 'cancelled'])) {
                        $order->update_status('failed');
                    }

                    \Nexi\OrderHelper::updateOrderMeta($order_id, '_xpay_last_error', $_POST["messaggio"]);

                    $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $_POST["messaggio"]);

                    $status = "200";
                    $payload = array(
                        "outcome" => "OK",
                        "order_id" => $order_id,
                    );
                }
            }

            \Nexi\OrderHelper::updateOrderMeta($order_id, '_xpay_post_notification_timestamp', time());
        } catch (\Exception $exc) {
            Log::actionInfo(__FUNCTION__ . ": " . $exc->getTraceAsString());
        }

        return new \WP_REST_Response($payload, $status, []);
    }

    public static function redirect($params)
    {
        $order_id = $params["id"];

        $order = new \WC_Order($order_id);

        $post_notification_timestamp = \Nexi\OrderHelper::getOrderMeta($order_id, '_xpay_post_notification_timestamp', true);

        //s2s not recived, so we need to update the order based the data recived in params
        if ($post_notification_timestamp == "") {
            Log::actionInfo(__FUNCTION__ . ": s2s notification for order id " . $order_id . " not recived, changing oreder status from request params");

            if ($params['esito'] == "OK") {
                if (!\in_array($order->get_status(), ['completed', 'processing'])) {
                    WC_Save_Order_Meta::saveSuccessXPay(
                        $order_id,
                        $params['alias'],
                        WC_Nexi_Helper::nexi_array_key_exists($params, 'num_contratto') ? $params['num_contratto'] : '',
                        $params['codTrans'],
                        $params['scadenza_pan']
                    );

                    $order->payment_complete($params["codTrans"]);
                }
            } else if ($params['esito'] == "PEN") {
                if ($order->get_status() != 'pd-pending-status') {
                    // if order in this status, it is considerated as completed/payed
                    $order->update_status('pd-pending-status');
                }
            } else {
                if (!\in_array($order->get_status(), ['failed', 'cancelled'])) {
                    $order->update_status('failed');
                }

                \Nexi\OrderHelper::updateOrderMeta($order_id, '_xpay_last_error', $params["messaggio"]);

                $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $params["messaggio"]);
            }
        }

        Log::actionInfo(__FUNCTION__ . ": user redirect for order id " . $order_id . ' - ' . (array_key_exists('esito', $params) ? $params['esito'] : ''));

        if ($order->needs_payment() || $order->get_status() == 'cancelled') {
            $lastErrorXpay = \Nexi\OrderHelper::getOrderMeta($order_id, '_xpay_last_error', true);

            if ($lastErrorXpay != "") {
                if (isset(WC()->session)) {
                    wc_add_notice(__('Payment error, please try again', 'woocommerce-gateway-nexi-xpay') . " (" . htmlentities($lastErrorXpay) . ")", 'error');
                }
            }

            $paymentErrorXpay = \Nexi\OrderHelper::getOrderMeta($order_id, '_xpay_payment_error', true);

            if ($paymentErrorXpay != "") {
                if (isset(WC()->session)) {
                    wc_add_notice(htmlentities($paymentErrorXpay), 'error');
                }
            }

            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        wp_safe_redirect($order->get_checkout_order_received_url());
        exit;
    }

    public static function cancel($params)
    {
        $order_id = $params["id"];

        if (($params['esito'] ?? '') === "ERRORE" && $params['warning']) {
            if (stripos($params['warning'], 'deliveryMethod') !== false) {
                $message = __('It was not possible to process the payment, check that the shipping address set is correct.', 'woocommerce-gateway-nexi-xpay');
            } else {
                $message = __('Payment canceled: ', 'woocommerce-gateway-nexi-xpay') . $params['warning'];
            }
        } else {
            $message = __('Payment has been cancelled.', 'woocommerce-gateway-nexi-xpay');
        }

        \Nexi\OrderHelper::updateOrderMeta($order_id, '_xpay_payment_error', $message);

        $order = new \WC_Order($order_id);

        wp_safe_redirect($order->get_cancel_order_url_raw());
        exit;
    }

    public static function process_account($data)
    {
        try {
            $params = $data->get_params();
            $order_id = $params["id"];

            $amount = WC_Nexi_Helper::mul_bcmul($_POST['amount'], 100, 0);

            if (!is_numeric($amount)) {
                throw new \Exception(__('Invalid amount.', 'woocommerce-gateway-nexi-xpay'));
            }

            $order = new \WC_Order($order_id);

            $codTrans = WC_Nexi_Helper::get_xpay_post_meta($order_id, 'codTrans');

            if (empty($codTrans)) {
                throw new \Exception(\sprintf(__('Unable to capture order %s. Order does not have XPay capture reference.', 'woocommerce-gateway-nexi-xpay'), $order_id));
            }

            return WC_Gateway_XPay_API::getInstance()->account($codTrans, $amount, $order->get_currency());
        } catch (\Exception $exc) {
            return new \WP_Error("broke", $exc->getMessage());
        }
    }

}
