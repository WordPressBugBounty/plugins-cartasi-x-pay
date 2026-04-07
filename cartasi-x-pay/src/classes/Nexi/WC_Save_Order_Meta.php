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

if (!defined('ABSPATH') ) {
    exit;
}

class WC_Save_Order_Meta
{
    private static function maskPaymentInstrumentInfo($value)
    {
        $value = (string) $value;
        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) >= 4) {
            return '****' . substr($digits, -4);
        }

        return $value;
    }

    private static function normalizeCustomerInfo($customerInfo)
    {
        if (!is_array($customerInfo)) {
            return [];
        }

        $ret = [];
        foreach (['cardHolderName', 'cardHolderEmail'] as $key) {
            if (isset($customerInfo[$key])) {
                $ret[$key] = $customerInfo[$key];
            }
        }

        return $ret;
    }


    public static function saveSuccessXPay($order_id, $alias, $num_contratto, $codTrans, $scadenza_pan)
    {
        $metaPrefix = "_xpay_";

        \Nexi\OrderHelper::deleteOrderMeta($order_id, $metaPrefix . "last_error");

        if (
            function_exists("wcs_is_subscription") && wcs_is_subscription($order_id) ||
            (function_exists("wcs_order_contains_subscription") &&
                (wcs_order_contains_subscription($order_id) || wcs_order_contains_renewal($order_id))
            )
        ) {
            if (get_option("woocommerce_subscriptions_turn_off_automatic_payments") !== "yes") {

                $subscriptions = wcs_get_subscriptions_for_order($order_id);
                foreach ($subscriptions as $subscription) {

                    \Nexi\Log::actionDebug("xpay subscription: " . json_encode($subscription));

                    $subscription_id = $subscription->get_id();

                    \Nexi\OrderHelper::updateOrderMeta($subscription_id, $metaPrefix . "alias", $alias);
                    \Nexi\OrderHelper::updateOrderMeta($subscription_id, $metaPrefix . "num_contratto", $num_contratto);
                    \Nexi\OrderHelper::updateOrderMeta($subscription_id, $metaPrefix . "codTrans", $codTrans);
                    \Nexi\OrderHelper::updateOrderMeta($subscription_id, $metaPrefix . "scadenza_pan", $scadenza_pan);

                }
            }
        }

        \Nexi\OrderHelper::updateOrderMeta($order_id, $metaPrefix . "alias", $alias);
        \Nexi\OrderHelper::updateOrderMeta($order_id, $metaPrefix . "num_contratto", $num_contratto);
        \Nexi\OrderHelper::updateOrderMeta($order_id, $metaPrefix . "codTrans", $codTrans);
        \Nexi\OrderHelper::updateOrderMeta($order_id, $metaPrefix . "scadenza_pan", $scadenza_pan);

    }


    public static function saveSuccessNpg($order_id, $authorization)
    {
        $metaPrefix = "_npg_";
        $authorizationSafe = $authorization;

        if (isset($authorizationSafe['paymentInstrumentInfo'])) {
            $authorizationSafe['paymentInstrumentInfo'] = self::maskPaymentInstrumentInfo($authorizationSafe['paymentInstrumentInfo']);
        }
        if (isset($authorizationSafe['customerInfo'])) {
            $authorizationSafe['customerInfo'] = self::normalizeCustomerInfo($authorizationSafe['customerInfo']);
        }

        \Nexi\OrderHelper::deleteOrderMeta($order_id, $metaPrefix . "last_error");

        // if it is a subscription, in addition to the order a subscription order is created, in which we need to save some information about the original order
        // doing so, when a new recurring payment is made for the same order, this data is copied automatically to the new order and can be used in the payment process
        if (
            (function_exists("wcs_is_subscription") && wcs_is_subscription($order_id)) ||
            (function_exists("wcs_order_contains_subscription") && (wcs_order_contains_subscription($order_id) || wcs_order_contains_renewal($order_id)))
        ) {
            if (get_option("woocommerce_subscriptions_turn_off_automatic_payments") !== "yes") {

                $subscriptions = wcs_get_subscriptions_for_order($order_id);
                foreach ($subscriptions as $subscription) {

                    \Nexi\Log::actionDebug("npg subscription: " . json_encode($subscription));

                    $subscription_id = $subscription->get_id();

                    foreach (["orderId", "paymentMethod", "paymentCircuit", "operationCurrency", "customerInfo"] as $var_name) {
                        if (\Nexi\WC_Nexi_Helper::nexi_array_key_exists($authorizationSafe, $var_name)) {
                            \Nexi\OrderHelper::updateOrderMeta($subscription_id, $metaPrefix . $var_name, $authorizationSafe[$var_name]);
                        }
                    }

                    \Nexi\OrderHelper::updateOrderMeta($subscription_id, $metaPrefix . "recurringContractId", \Nexi\OrderHelper::getOrderMeta($order_id, $metaPrefix . 'recurringContractId', true));
                }
            }
        }

        foreach (["orderId", "operationId", "operationType", "operationResult", "operationTime", "paymentMethod", "paymentCircuit", "paymentInstrumentInfo", "paymentEndToEndId", "cancelledOperationId", "operationAmount", "operationCurrency", "customerInfo"] as $var_name) {
            if (\Nexi\WC_Nexi_Helper::nexi_array_key_exists($authorizationSafe, $var_name)) {
                \Nexi\OrderHelper::updateOrderMeta($order_id, $metaPrefix . $var_name, $authorizationSafe[$var_name]);
            }
        }

    }
}
