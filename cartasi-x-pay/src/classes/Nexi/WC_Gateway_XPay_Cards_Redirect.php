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

class WC_Gateway_XPay_Cards_Redirect extends WC_Gateway_XPay_Cards
{

    public function filter_saved_payment_methods_list($list, $customer_id)
    {
        return [];
    }

    public function payment_fields()
    {
        parent::payment_fields();

        echo $this->get_xpay_cards_icon();
    }

}
