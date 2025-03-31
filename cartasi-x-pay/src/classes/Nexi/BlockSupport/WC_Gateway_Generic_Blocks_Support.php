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

abstract class WC_Gateway_Generic_Blocks_Support extends AbstractPaymentMethodType
{

    public function __construct($name, $gateway, $gatewaySettingsKey, $apm = '', $isBuild = false)
    {
        $this->name = $name;
        $this->gateway = $gateway;
        $this->gatewaySettingsKey = $gatewaySettingsKey;
        $this->apm = $apm;
        $this->isBuild = $isBuild;
    }

    /**s
     * This property is a string used to reference your payment method. It is important to use the same name as in your
     * client-side JavaScript payment method registration.
     *
     * @var string
     */
    protected $name;

    private $gateway;

    private $gatewaySettingsKey;

    protected $apm;

    protected $isBuild;

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
        return $this->is_active_internal() && $this->is_active_method_specific();
    }

    private function is_active_internal()
    {
        $gatewaySettings = null;

        if ($this->isBuild) {
            $gatewaySettings = \WC_Admin_Settings::get_option('woocommerce_xpay_build_settings') ?? [];
        } else {
            $gatewaySettings = \WC_Admin_Settings::get_option('woocommerce_xpay_settings') ?? [];
        }

        if (empty($gatewaySettings) || ($gatewaySettings['enabled'] ?? '') !== 'yes' || ($gatewaySettings['nexi_gateway'] ?? '') !== $this->gateway) {
            return false;
        }
        if ($this->apm !== null && !empty($this->apm)) {
            $apmSettings = \WC_Admin_Settings::get_option('woocommerce_' . $this->gatewaySettingsKey . '_' . $this->apm . '_settings') ?? [];
            if (empty($apmSettings) || $apmSettings['enabled' ?? ''] !== 'yes') {
                return false;
            }
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
        $baseFilename = 'index_' . $this->name;
        $baseName = str_replace('_', '-', $this->name);

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
            'label' => $this->getLabel(),
            'content' => $this->getContent(),
            'icons' => $this->getIcons(),
            'content_icons' => $this->getContentIcons(),
            'features' => $features,
            'show_saved_cards' => $savedCardsSupport,
            'show_save_option' => $savedCardsSupport,
            'can_make_payment' => $this->is_active(),
            'installments' => $this->getInstallmentsInfo(),
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

    protected function getInstallmentsInfo()
    {
        return [
            'enabled' => false,
            'options' => [],
            'default_option' => '',
            'title_text' => '',
            'one_solution_text' => '',
        ];
    }

    protected function getRecurringInfo()
    {
        return [
            'enabled' => false,
            'disclaimer_text' => ''
        ];
    }

    protected function is_active_method_specific()
    {
        return true;
    }

    abstract protected function getLabel();

    abstract protected function getContent();

    abstract protected function getIcons();

    abstract protected function getContentIcons();

}
