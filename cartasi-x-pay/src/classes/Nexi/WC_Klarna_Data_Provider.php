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
        $params = array();

        try {
            $params['nome'] = $order->get_billing_first_name();
            $params['cognome'] = $order->get_billing_last_name();

            $allItems = $order->get_items();

            $itemsNumber = 0;

            $itemsAmountCalculated = 0;

            foreach ($allItems as $item) {
                $itemsNumber++;

                $params['Item_quantity_' . $itemsNumber] = $item->get_quantity();
                $params['Item_amount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($item->get_product()->get_price(), 100, 0);
                $params['Item_name_' . $itemsNumber] = self::escapeKlarnaSpecialCharacters($item->get_name());

                $itemsAmountCalculated += $item->get_total() * $item->get_quantity();
            }

            $extraFee = $order->get_total() - $itemsAmountCalculated;

            if ($extraFee > 0) {
                $itemsNumber++;

                $params['Item_quantity_' . $itemsNumber] = 1;
                $params['Item_amount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($extraFee, 100, 0);
                $params['Item_name_' . $itemsNumber] = self::escapeKlarnaSpecialCharacters("Extra Fee");
            }

            $params['itemsNumber'] = $itemsNumber;

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
