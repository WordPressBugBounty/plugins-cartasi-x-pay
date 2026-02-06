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

class WC_Gateway_NPG_Google_Pay_Button_Blocks_Support extends WC_Gateway_NPG_Blocks_Support
{

    private $title;
    private $description;
    private $image;

    public function __construct($title, $description, $image)
    {
        parent::__construct('googlepay_button');

        $this->apm = 'googlepay';
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
            "{$this->apm}-nexipay" => [
                'src' => $this->image,
                'alt' => "{$this->title} logo",
            ]
        ];
    }

    protected function getContentIcons()
    {
        return [];
    }

    protected function savedCardsSupport()
    {
        return false;
    }

    protected function getRecurringInfo()
    {
        return [
            'enabled' => \Nexi\WC_Gateway_Nexi_Register_Available::is_npg_recurring($this->apm) && \Nexi\WC_Nexi_Helper::cart_contains_subscription(),
            'disclaimer_text' => __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay'),
        ];
    }

}
