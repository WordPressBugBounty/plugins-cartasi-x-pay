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

namespace Nexi\BlockSupport;

abstract class WC_Gateway_Xpay_Blocks_Support extends WC_Gateway_Generic_Blocks_Support
{

    public function __construct(
        $apm = '',
        $isBuild = false,
    ) {
        if ($apm !== null && !empty($apm)) {
            parent::__construct('xpay_' . strtolower($apm), 'xpay', 'xpay', $apm, $isBuild);
        } else {
            parent::__construct('xpay', 'xpay', 'xpay', $apm, $isBuild);
        }
    }

}
