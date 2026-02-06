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

class WC_Gateway_NPG_APM_Blocks_Support extends WC_Gateway_NPG_Blocks_Support
{

    private $apmCode;
    private $title;
    private $description;
    private $image;

    public function __construct($apmCode, $title, $description, $image)
    {
        parent::__construct($apmCode);

        $this->apmCode = $apmCode;
        $this->title = $title;
        $this->description = $description;
        $this->image = $image;
    }

    protected function getLabel()
    {
        return $this->title;
    }

    protected function getContent()
    {
        return $this->description;
    }

    protected function getIcons()
    {
        return [
            "{$this->apmCode}-nexipay" => [
                'src' => $this->image,
                'alt' => "{$this->title} logo",
            ]
        ];
    }

    protected function getContentIcons()
    {
        return [];
    }

    protected function get_min_amount()
    {
        return \Nexi\WC_Gateway_Nexi_Register_Available::get_npg_min_amount($this->apmCode);
    }

    protected function get_max_amount()
    {
        return \Nexi\WC_Gateway_Nexi_Register_Available::get_npg_max_amount($this->apmCode);
    }

    protected function getRecurringInfo()
    {
        return [
            'enabled' => \Nexi\WC_Gateway_Nexi_Register_Available::is_npg_recurring($this->apmCode) && \Nexi\WC_Nexi_Helper::cart_contains_subscription(),
            'disclaimer_text' => __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay'),
        ];
    }

}
