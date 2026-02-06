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

class WC_XPay_Checkout extends \WC_Checkout
{

    public function public_validate_checkout(&$data, &$errors)
    {
        $this->validate_checkout($data, $errors);
    }

    public static function validate_checkout_form()
    {
        $xpayCheckout = new self();

        $errors = new \WP_Error();
        $posted_data = $xpayCheckout->get_posted_data();

        $xpayCheckout->public_validate_checkout($posted_data, $errors);

        wp_send_json([
            'errors' => $errors->errors,
        ]);
    }

}
