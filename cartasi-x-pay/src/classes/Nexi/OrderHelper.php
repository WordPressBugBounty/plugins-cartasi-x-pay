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
                        \Nexi\Log::actionDebug("exception: " . json_encode($e));
                    }
                }

                try {
                    $order = new \WC_Order($orderId);
                    $meta = $order->get_meta($metaKey, $single);
                    return $meta;
                } catch (\Exception $e) {
                    \Nexi\Log::actionDebug("exception: " . json_encode($e));
                }
            }
        } catch (\Exception $e) {
            \Nexi\Log::actionDebug("exception: " . json_encode($e));
        }

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
                        \Nexi\Log::actionDebug("subscription exception: " . json_encode($e));
                    }
                }

                try {
                    $order = new \WC_Order($orderId);
                    $order->update_meta_data($metaKey, $value);
                    $order->save_meta_data();
                } catch (\Exception $e) {
                    \Nexi\Log::actionDebug("order exception: " . json_encode($e));
                }
            }
        } catch (\Exception $e) {
            \Nexi\Log::actionDebug("exception: " . json_encode($e));
        }

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
            \Nexi\Log::actionDebug("exception: " . json_encode($e));
        }
        delete_post_meta($orderId, $metaKey);
    }

    public static function get3ds20OrderInLastSixMonth()
    {
        $orders = null;
        if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $args = [
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'desc',
                'customer_id' => get_current_user_id(),
                'date_created' => '>' . date('Y-m-d', strtotime('- 6 month')),
            ];
            $orders = wc_get_orders($args);
        } else {
            $args = array(
                'numberposts' => -1,
                'meta_key' => '_customer_user',
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_value' => get_current_user_id(),
                'post_type' => wc_get_order_types(),
                'post_status' => array_keys(wc_get_order_statuses()),
                'date_query' => array(
                    'after' => date('Y-m-d', strtotime('- 6 month'))
                )
            );
            $orders = get_posts($args);
        }
        return count($orders);
    }

}
