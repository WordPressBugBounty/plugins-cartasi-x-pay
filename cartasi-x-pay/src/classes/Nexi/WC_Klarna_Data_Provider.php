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

use Exception;

class WC_Klarna_Data_Provider
{

    public static function calculate_params($order)
    {
        $params = [];

        try {
            $params['nome'] = $order->get_billing_first_name();
            $params['cognome'] = $order->get_billing_last_name();

            $itemsNumber = 0;

            foreach ($order->get_items('line_item') as $item) {
                $itemsNumber++;

                $product = $item->get_product();

                $params['Item_quantity_' . $itemsNumber] = $item->get_quantity();
                $params['Item_amount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($product->get_regular_price(), 100, 0);
                $params['Item_name_' . $itemsNumber] = self::escapeKlarnaSpecialCharacters($item->get_name());

                if ($product->get_sale_price()) {
                    $singleProductDiscount = ((float) $product->get_regular_price()) - ((float) $product->get_sale_price());
                } else {
                    $singleProductDiscount = 0;
                }

                if ($singleProductDiscount) {
                    $totalProductDiscount = WC_Nexi_Helper::mul_bcmul($singleProductDiscount, $item->get_quantity());

                    $params['Item_totalDiscountAmount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($totalProductDiscount, 100, 0);
                } else {
                    $params['Item_totalDiscountAmount_' . $itemsNumber] = 0;
                }

                if ($product->get_virtual()) {
                    $params['Item_type_' . $itemsNumber] = "DIGITAL";
                } else {
                    $params['Item_type_' . $itemsNumber] = "PHYSICAL";
                }
            }

            foreach ($order->get_items('shipping') as $shippingLine) {
                $itemsNumber++;

                $params['Item_name_' . $itemsNumber] = __('Shipping', 'woocommerce-gateway-nexi-xpay');
                $params['Item_quantity_' . $itemsNumber] = 1;
                $params['Item_amount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($shippingLine->get_total(), 100, 0);
                $params['Item_type_' . $itemsNumber] = "SHIPPING_FEE";
                $params['Item_totalDiscountAmount_' . $itemsNumber] = 0;
            }

            foreach ($order->get_items('coupon') as $couponLine) {
                $itemsNumber++;

                $params['Item_name_' . $itemsNumber] = "Coupon";
                $params['Item_quantity_' . $itemsNumber] = 1;
                $params['Item_amount_' . $itemsNumber] = "-" . WC_Nexi_Helper::mul_bcmul($couponLine->get_discount(), 100, 0);
                $params['Item_type_' . $itemsNumber] = "DISCOUNT";
                $params['Item_totalDiscountAmount_' . $itemsNumber] = 0;
            }

            foreach ($order->get_items('tax') as $taxLine) {
                if ($taxLine->get_tax_total()) {
                    $itemsNumber++;

                    $params['Item_name_' . $itemsNumber] = "Tax";
                    $params['Item_quantity_' . $itemsNumber] = 1;
                    $params['Item_amount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($taxLine->get_tax_total(), 100, 0);
                    $params['Item_type_' . $itemsNumber] = "SURCHARGE";
                    $params['Item_totalDiscountAmount_' . $itemsNumber] = 0;
                }

                if ($taxLine->get_shipping_tax_total()) {
                    $itemsNumber++;

                    $params['Item_name_' . $itemsNumber] = "Shipping Tax";
                    $params['Item_quantity_' . $itemsNumber] = 1;
                    $params['Item_amount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($taxLine->get_shipping_tax_total(), 100, 0);
                    $params['Item_type_' . $itemsNumber] = "SURCHARGE";
                    $params['Item_totalDiscountAmount_' . $itemsNumber] = 0;
                }
            }

            $params['itemsNumber'] = $itemsNumber;
            $params['itemsAmount'] = WC_Nexi_Helper::mul_bcmul($order->get_total(), 100, 0);

            if ($order->has_shipping_address()) {
                $params['Dest_city'] = $order->get_shipping_city();
                $params['Dest_country'] = Iso3166::getAlpha3($order->get_shipping_country());
                $params['Dest_street'] = $order->get_shipping_address_1();
                $params['Dest_street2'] = $order->get_shipping_address_2();
                $params['Dest_cap'] = $order->get_shipping_postcode();
                $params['Dest_state'] = CapToStateCode::getStateCode($order->get_shipping_postcode());
                $params['Dest_name'] = $order->get_shipping_first_name();
                $params['Dest_surname'] = $order->get_shipping_last_name();
            } else {
                $params['Dest_city'] = $order->get_billing_city();
                $params['Dest_country'] = Iso3166::getAlpha3($order->get_billing_country());
                $params['Dest_street'] = $order->get_billing_address_1();
                $params['Dest_street2'] = $order->get_billing_address_2();
                $params['Dest_cap'] = $order->get_billing_postcode();
                $params['Dest_state'] = CapToStateCode::getStateCode($order->get_billing_postcode());
                $params['Dest_name'] = $order->get_billing_first_name();
                $params['Dest_surname'] = $order->get_billing_last_name();
            }

            $params['Bill_city'] = $order->get_billing_city();
            $params['Bill_country'] = Iso3166::getAlpha3($order->get_billing_country());
            $params['Bill_street'] = $order->get_billing_address_1();
            $params['Bill_street2'] = $order->get_billing_address_2();
            $params['Bill_cap'] = $order->get_billing_postcode();
            $params['Bill_state'] = CapToStateCode::getStateCode($order->get_billing_postcode());
            $params['Bill_name'] = $order->get_billing_first_name();
            $params['Bill_surname'] = $order->get_billing_last_name();
        } catch (Exception $exc) {
            Log::actionWarning($exc->getMessage());
        }

        return $params;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private static function escapeKlarnaSpecialCharacters($str)
    {
        $pattern = "/[^a-zA-Z0-9!@#$%^&() _+\\-=\\[\\]{};':\"\\|,.?]/";

        return preg_replace($pattern, '', $str);
    }

}
