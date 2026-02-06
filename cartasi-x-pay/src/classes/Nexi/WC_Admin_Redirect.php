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

class WC_Admin_Redirect
{

    public static function init()
    {
        if (!is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? wc_clean(wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? wc_clean(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? wc_clean(wp_unslash($_GET['section'])) : '';

        if ($page !== 'wc-settings' || $tab !== 'checkout') {
            return;
        }

        if (stripos($section, 'xpay_') !== false) {
            $redirect_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=xpay');

            wp_safe_redirect($redirect_url);
            exit;
        }
    }

}
