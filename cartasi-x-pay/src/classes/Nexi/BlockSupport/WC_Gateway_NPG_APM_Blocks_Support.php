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
    private $label;
    private $description;

    public function __construct(
        $apmCode,
        $apmLabel,
        $apmDescription,
        $isBuild = false,
    ) {
        parent::__construct($apmCode, $isBuild);
        $this->apmCode = $apmCode;
        $this->label = $apmLabel;
        $this->description = $apmDescription;
    }

    protected function getLabel()
    {
        return __($this->label, WC_LANG_KEY);
    }

    protected function getContent()
    {
        return __($this->description, WC_LANG_KEY);
    }

    protected function getIcons()
    {
        $available_methods_npg = json_decode(\WC_Admin_Settings::get_option('xpay_npg_available_methods'), true);

        $icons = [];

        if (is_array($available_methods_npg)) {
            foreach ($available_methods_npg as $am) {
                if ($am['circuit'] !== $this->apmCode) {
                    continue;
                }
                $imageLink = $am['imageLink'] ?? '';
                if (!empty($imageLink) && $imageLink !== 'no image') {
                    $icons[$am['circuit'] . '-nexipay'] = [
                        'src' => $am['imageLink'],
                        'alt' => __($this->label . " logo", WC_LANG_KEY)
                    ];
                }
            }
        }

        return $icons;
    }

    protected function getContentIcons()
    {
        return [];
    }

}
