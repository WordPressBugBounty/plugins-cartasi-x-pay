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

if (!defined('ABSPATH')) {
    exit;
}

class OrderHelper
{

    public static function getOrderMeta($orderId, $metaKey, $single = false)
    {
        try {
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                if (function_exists("wcs_get_subscription")) {
                    try {
                        $subscription = wcs_get_subscription($orderId);

                        if ($subscription != null) {
                            $meta = $subscription->get_meta($metaKey, $single);

                            if (!$meta) {
                                if (strpos($metaKey, 'num_contratto') !== false || strpos($metaKey, 'scadenza_pan') !== false || strpos($metaKey, 'recurringContractId') !== false) {
                                    return get_post_meta($orderId, $metaKey, $single);
                                }
                            }

                            return $meta;
                        }
                    } catch (\Exception $e) {
                        \Nexi\Log::actionDebug(__FUNCTION__ . ' ' . __LINE__ . " exception: " . json_encode([$orderId, $metaKey, $e->getMessage()]));
                    }
                }

                try {
                    $order = new \WC_Order($orderId);
                    $meta = $order->get_meta($metaKey, $single);
                    return $meta;
                } catch (\Exception $e) {
                    \Nexi\Log::actionDebug(__FUNCTION__ . ' ' . __LINE__ . " exception: " . json_encode([$orderId, $metaKey, $e->getMessage()]));
                }
            }
        } catch (\Exception $e) {
            \Nexi\Log::actionDebug(__FUNCTION__ . ' ' . __LINE__ . " exception: " . json_encode([$orderId, $metaKey, $e->getMessage()]));
        }
        return get_post_meta($orderId, $metaKey, $single);
    }

    public static function getPostMeta($orderId, $metaKey, $single = false)
    {
        return get_post_meta($orderId, $metaKey, $single);
    }

    public static function updateOrderMeta($orderId, $metaKey, $value)
    {
        try {
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                if (function_exists("wcs_get_subscription")) {
                    try {
                        $subscription = wcs_get_subscription($orderId);
                        if ($subscription != null) {
                            $subscription->update_meta_data($metaKey, $value);
                            $subscription->save();
                        }
                    } catch (\Exception $e) {
                        \Nexi\Log::actionDebug(__FUNCTION__ . ' ' . __LINE__ . " subscription exception: " . json_encode($e));
                    }
                }

                try {
                    $order = new \WC_Order($orderId);
                    $order->update_meta_data($metaKey, $value);
                    $order->save_meta_data();
                } catch (\Exception $e) {
                    \Nexi\Log::actionDebug(__FUNCTION__ . ' ' . __LINE__ . " exception: " . json_encode([$orderId, $metaKey, $value, $e->getMessage()]));
                }
            }
        } catch (\Exception $e) {
            \Nexi\Log::actionDebug(__FUNCTION__ . ' ' . __LINE__ . " exception: " . json_encode([$orderId, $metaKey, $value, $e->getMessage()]));
        }
        update_post_meta($orderId, $metaKey, $value);
    }

    public static function updatePostMeta($orderId, $metaKey, $value)
    {
        update_post_meta($orderId, $metaKey, $value);
    }

    public static function deleteOrderMeta($orderId, $metaKey)
    {
        try {
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $order = new \WC_Order($orderId);
                $order->delete_meta_data($metaKey);
                $order->save_meta_data();
                return;
            }
        } catch (\Exception $e) {
            \Nexi\Log::actionDebug("exception: " . json_encode([$orderId, $metaKey, $e->getMessage()]));
        }
        delete_post_meta($orderId, $metaKey);
    }

    public static function get3ds20OrderInLastSixMonth()
    {
        $customerId = get_current_user_id();
        $customerEmail = '';

        if ($customerId <= 0) {
            $customerEmail = self::getCheckoutCustomerEmail();

            if ($customerEmail === '') {
                return 0;
            }
        }

        $orders = [];

        if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $args = [
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'desc',
                'return' => 'ids',
                'date_created' => '>' . gmdate('Y-m-d', strtotime('- 6 month')),
            ];

            if ($customerId > 0) {
                $args['customer_id'] = $customerId;
            } else {
                $args['billing_email'] = $customerEmail;
            }

            $orders = wc_get_orders($args);
        } else {
            $args = [
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
                'post_type' => wc_get_order_types(),
                'post_status' => array_keys(wc_get_order_statuses()),
                'date_query' => [
                    'after' => gmdate('Y-m-d', strtotime('- 6 month'))
                ]
            ];

            if ($customerId > 0) {
                $args['meta_key'] = '_customer_user';
                $args['meta_value'] = $customerId;
            } else {
                $args['meta_key'] = '_billing_email';
                $args['meta_value'] = $customerEmail;
            }

            $orders = get_posts($args);
        }

        return \count($orders);
    }

    private static function getCheckoutCustomerEmail()
    {
        if (function_exists('WC') && WC() && WC()->customer) {
            $billingEmail = (string) WC()->customer->get_billing_email();
            if ($billingEmail !== '') {
                return $billingEmail;
            }
        }

        return '';
    }

}
