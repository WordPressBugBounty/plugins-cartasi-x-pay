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

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_NPG_Build_Blocks_Support extends AbstractPaymentMethodType
{

    public function __construct()
    {
        $this->name = 'xpay_build';
        $this->gateway = 'npg';
    }

    /**s
     * This property is a string used to reference your payment method. It is important to use the same name as in your
     * client-side JavaScript payment method registration.
     *
     * @var string
     */
    protected $name;

    private $gateway;


    /**
     * Initializes the payment method.
     *
     * This function will get called during the server side initialization process and is a good place to put any settings
     * population etc. Basically anything you need to do to initialize your gateway.
     *
     * Note, this will be called on every request so don't put anything expensive here.
     */
    public function initialize()
    {
        $this->settings = get_option($this->name . '_settings', []);
    }

    /**
     * This should return whether the payment method is active or not.
     *
     * If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        $gatewaySettings = \WC_Admin_Settings::get_option('woocommerce_xpay_build_settings') ?? [];

        if (empty($gatewaySettings) || ($gatewaySettings['enabled'] ?? '') !== 'yes' || ($gatewaySettings['nexi_gateway'] ?? '') !== $this->gateway) {
            return false;
        }

        return true;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * In this function you should register your payment method scripts (using `wp_register_script`) and then return the
     * script handles you registered with. This will be used to add your payment method as a dependency of the checkout script
     * and thus take sure of loading it correctly.
     *
     * Note that you should still make sure any other asset dependencies your script has are registered properly here, if
     * you're using Webpack to build your assets, you may want to use the WooCommerce Webpack Dependency Extraction Plugin
     * (https://www.npmjs.com/package/@woocommerce/dependency-extraction-webpack-plugin) to make this easier for you.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $baseFilename = 'index_npg_' . $this->name;
        $baseName = 'npg-' . str_replace('_', '-', $this->name);

        $pluginScriptPath = WC_GATEWAY_XPAY_PLUGIN_URL . '/build/' . $baseFilename . '.js';
        $pluginStylePath = WC_GATEWAY_XPAY_PLUGIN_URL . '/build/' . $baseFilename . '.css';
        $asset_path = WC_GATEWAY_XPAY_PLUGIN_BASE_PATH . 'build/' . $baseFilename . '.asset.php';

        $version = "1.0.0";

        $dependencies = [];
        if (file_exists($asset_path)) {
            $asset = require $asset_path;
            $version = is_array($asset) && isset($asset['version'])
                ? $asset['version']
                : $version;
            $dependencies = is_array($asset) && isset($asset['dependencies'])
                ? $asset['dependencies']
                : $dependencies;
        }

        wp_enqueue_style(
            'wc-gateway-' . $baseName . '-blocks-integration-style',
            $pluginStylePath,
            [],
            $version
        );

        wp_register_script(
            'wc-gateway-' . $baseName . '-blocks-integration',
            $pluginScriptPath,
            $dependencies,
            $version,
            true
        );

        return ['wc-gateway-' . $baseName . '-blocks-integration'];
    }

    /**
     * Returns an array of script handles to be enqueued for the admin.
     *
     * Include this if your payment method has a script you _only_ want to load in the editor context for the checkout block.
     * Include here any script from `get_payment_method_script_handles` that is also needed in the admin.
     */
    public function get_payment_method_script_handles_for_admin()
    {
        return $this->get_payment_method_script_handles();
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script client side.
     *
     * This data will be available client side via `wc.wcSettings.getSetting`. So for instance if you assigned `stripe` as the
     * value of the `name` property for this class, client side you can access any data via:
     * `wc.wcSettings.getSetting( 'stripe_data' )`. That would return an object matching the shape of the associative array
     * you returned from this function.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        $recurring = $this->getRecurringInfo();
        $recurringEnabled = $recurring['enabled'];

        $savedCardsSupport = !$recurringEnabled && $this->savedCardsSupport();

        $features = ['products'];
        if ($recurringEnabled) {
            $features[] = 'subscriptions';
        } else if ($savedCardsSupport) {
            $features[] = 'tokenization';
            $features[] = 'add_payment_method';
        }

        return [
            'label' => __('Payment cards', WC_LANG_KEY),
            'content' => __("Pay securely by credit, debit and prepaid card. Powered by Nexi.", WC_LANG_KEY),
            'validation_error' => __('Incorrect or missing data', WC_LANG_KEY),
            'session_error' => __('XPay Build session expired', WC_LANG_KEY),
            'admin_url' => admin_url(),
            'icons' => $this->getContentIcons(),
            'content_icons' => $this->getContentIcons(),
            'features' => $features,
            'show_saved_cards' => $savedCardsSupport,
            'show_save_option' => $savedCardsSupport,
            'can_make_payment' => $this->is_active(),
            'recurring' => $recurring,
        ];
    }

    public function getName()
    {
        return $this->name;
    }

    protected function savedCardsSupport()
    {
        return false;
    }

    protected function getIcons()
    {
        return [
            'xpay-nexipay' => [
                'src' => \WC_Admin_Settings::get_option('xpay_logo_small'),
                'alt' => __("Nexi pay logo", WC_LANG_KEY)
            ],
        ];
    }

    protected function getContentIcons()
    {
        $available_methods_npg = json_decode(\WC_Admin_Settings::get_option('xpay_npg_available_methods'), true);

        $contentIcons = [];

        if (is_array($available_methods_npg)) {
            foreach ($available_methods_npg as $am) {
                if ($am['paymentMethodType'] != "CARDS") {
                    continue;
                }
                $imageLink = $am['imageLink'] ?? '';
                if (!empty($imageLink) && $imageLink !== 'no image') {
                    $contentIcons[$am['circuit'] . '-nexipay'] = [
                        'src' => $am['imageLink'],
                        'alt' => __($am['circuit'] . " logo", WC_LANG_KEY)
                    ];
                }
            }
        }

        return $contentIcons;
    }

    protected function getRecurringInfo()
    {
        return [
            'enabled' => \Nexi\WC_Nexi_Helper::cart_contains_subscription(),
            'disclaimer_text' => __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay')
        ];
    }

}
