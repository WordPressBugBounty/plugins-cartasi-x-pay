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

class WC_Gateway_NPG_API extends \WC_Settings_API
{

    private static $instance = null;

    /**
     *
     * @return \Nexi\WC_Gateway_NPG_API
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public $id = 'xpay';
    private $nexi_npg_api_key;
    private $base_url = null;
    private $nexi_xpay_accounting;
    private $nexi_xpay_oneclick_enabled;
    private $nexi_xpay_3ds20_enabled;
    private $nexi_xpay_recurring_enabled;

    private function __construct()
    {
        $this->init_settings();

        if ($this->settings["nexi_xpay_test_mode"] == "no") {
            $this->base_url = 'https://xpay.nexigroup.com';
        } else {
            $this->base_url = 'https://stg-ta.nexigroup.com';
        }

        $this->nexi_npg_api_key = $this->settings["nexi_npg_api_key"];

        $this->nexi_xpay_accounting = $this->settings["nexi_xpay_accounting"];

        $this->nexi_xpay_oneclick_enabled = (($this->settings["nexi_xpay_oneclick_enabled"] ?? "no") == "yes");

        $this->nexi_xpay_3ds20_enabled = (($this->settings['nexi_xpay_3ds20_enabled'] ?? "no") == 'yes');

        $this->nexi_xpay_recurring_enabled = (($this->settings["nexi_xpay_recurring_enabled"] ?? "no") == "yes");
    }

    public function getUrlNpgBuildJS()
    {
        return "{$this->base_url}/monetaweb/resources/hfsdk.js";
    }

    public function get_profile_info()
    {
        if (strlen($this->nexi_npg_api_key) == 0) {
            delete_option('xpay_npg_available_methods');
            return null;
        }

        try {
            $response = $this->exec_get('payment_methods');

            if ($response['status_code'] !== 200) {
                throw new \Exception('Credentials error - ' . json_encode($response));
            }

            $payment_methods_data = $response['response'];

            if (!isset($payment_methods_data['paymentMethods'])) {
                throw new \Exception('Missing paymentMethods - ' . json_encode($response));
            }

            update_option('xpay_npg_available_methods', json_encode($payment_methods_data['paymentMethods']));

            self::enable_apms();

            return $payment_methods_data;
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Error while checking credentials.', 'woocommerce-gateway-nexi-xpay'));
        }
    }

    public static function enable_apms()
    {
        \Nexi\WC_Nexi_Helper::enable_payment_method(WC_SETTINGS_KEY);

        foreach (\Nexi\WC_Nexi_Helper::get_npg_available_methods() as $method) {
            if ($method['paymentMethodType'] === 'APM') {
                \Nexi\WC_Nexi_Helper::enable_payment_method("woocommerce_xpay_npg_" . $method['circuit'] . "_settings");

                if (in_array(strtolower($method['circuit']), ['googlepay', 'applepay'])) {
                    \Nexi\WC_Nexi_Helper::enable_payment_method("woocommerce_xpay_npg_" . $method['circuit'] . "_button_settings");
                }
            }
        }
    }

    private function get_result_url($nexiOrderId)
    {
        return add_query_arg(
            ['nexi_order_id' => $nexiOrderId],
            wc_get_endpoint_url('woocommerce-gateway-nexi-npg-redirect', '', home_url('/'))
        );
    }

    private function get_cancel_url($nexiOrderId)
    {
        return add_query_arg(
            ['nexi_order_id' => $nexiOrderId],
            wc_get_endpoint_url('woocommerce-gateway-nexi-npg-cancel', '', home_url('/'))
        );
    }

    public function new_payment_link($order, $recurringPayment, $selectedToken, $saveCard, $selectedC, $installmentsNumber)
    {
        try {
            $amount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit($order->get_total(), $order->get_currency());

            $orderId = $this->generate_npg_order_id(18, $order->get_order_number());

            $customerId = $order->get_customer_id();

            $payload = array(
                "order" => array(
                    "orderId" => $orderId,
                    "invoiceId" => substr("Order-" . $order->get_order_number(), 0, 30),
                    "amount" => $amount,
                    "currency" => $order->get_currency(),
                    "description" => "WC Order " . $order->get_id(),
                    "customField" => "Woocommerce " . WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION . " - nexixpay " . WC_GATEWAY_XPAY_VERSION,
                ),
                "paymentSession" => array(
                    "actionType" => "PAY",
                    "amount" => $amount,
                    "recurrence" => array(
                        "action" => NPG_NO_RECURRING
                    ),
                    "exemptions" => "NO_PREFERENCE",
                    "language" => WC_Gateway_XPay_Generic_Method::get_language_id(),
                    "resultUrl" => $this->get_result_url($order->get_id()),
                    "cancelUrl" => $this->get_cancel_url($order->get_id()),
                    "notificationUrl" => get_rest_url(null, "woocommerce-gateway-nexi-xpay/s2s/npg/" . $order->get_id()),
                    "paymentService" => $selectedC
                )
            );

            if (is_user_logged_in() && $customerId) {
                $payload["order"]["customerId"] = $customerId;
            }

            if ($installmentsNumber && $installmentsNumber >= 2) {
                $payload["order"]["plan"] = array(
                    "planType" => "ACQUIRER_AGREEMENT",
                    "installmentQty" => $installmentsNumber,
                );

                \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_npg_installmentsNumber", $installmentsNumber);
            }

            if ($recurringPayment) {
                if (!$this->nexi_xpay_recurring_enabled) {
                    Log::actionWarning(__FUNCTION__ . ": recurring payment for non recurring payment method");
                    throw new \Exception("Recurring not enabled");
                }

                $payload = $this->get_recurring_params($payload, $customerId);

                \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_npg_recurringContractId", $payload['paymentSession']['recurrence']['contractId']);
            } else if (is_user_logged_in() && $this->nexi_xpay_oneclick_enabled) {
                $payload = $this->get_one_click_params($payload, $customerId, $selectedToken, $saveCard);
            }

            if ($this->nexi_xpay_3ds20_enabled) {
                $payload['order']['customerInfo'] = WC_NPG_3DS20_Data_Provider::calculate_params($order);
            }

            $response = $this->exec_post("orders/hpp", $payload);

            if ($response['status_code'] !== 200) {
                throw new \Exception('Error while initializing the payment - ' . json_encode($response));
            }

            \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_npg_securityToken", $response['response']['securityToken']);
            \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_npg_orderId", $orderId);

            if ($payload['paymentSession']['recurrence']['action'] == NPG_CONTRACT_CREATION) {
                \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_npg_contractId", $payload['paymentSession']['recurrence']['contractId']);
            }

            return $response['response']["hostedPage"];
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Unable to initialize the payment.', 'woocommerce-gateway-nexi-xpay'));
        }
    }

    /**
     *
     */
    private function get_recurring_params($payload, $customerId)
    {
        $payload['paymentSession']['recurrence'] = [
            'action' => NPG_CONTRACT_CREATION,
            'contractId' => $this->generate_contract_id($customerId, 'RP'),
        ];

        try {
            $response = $this->get_customer_contracts($customerId);
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());
        }

        if (isset($response) && $response) {
            foreach ($response['contracts'] as $contract) {
                if ($contract['recurringContractType'] == NPG_RT_MIT_UNSCHEDULED) {
                    $payload['paymentSession']['recurrence'] = [
                        'action' => NPG_SUBSEQUENT_PAYMENT,
                        'contractId' => $contract['contractId'],
                    ];
                    break;
                }
            }
        }

        $payload['paymentSession']['recurrence']['contractType'] = NPG_RT_MIT_UNSCHEDULED;

        return $payload;
    }

    private function get_one_click_params($payload, $customerId, $selectedToken, $saveCard)
    {
        $contractId = null;

        if ($selectedToken && $selectedToken != 'new') {
            $token = WC_NPG_Token::get_token_nexi($selectedToken, (int) $customerId);

            if ($token === false) {
                Log::actionWarning(__FUNCTION__ . ': Invalid selected card');
                return $payload;
            }

            $response = $this->get_customer_one_click_contracts($customerId);

            $contractFound = false;

            if ($response['has_contracts'] && count($response['contracts']) > 0) {
                foreach ($response['contracts'] as $contract) {
                    if ($contract['contractId'] == $token->get_token()) {
                        $contractFound = $contract;
                        break;
                    }
                }
            }

            if ($contractFound !== false) {
                $payload['paymentSession']['recurrence']['action'] = NPG_SUBSEQUENT_PAYMENT;
                $contractId = $contractFound['contractId'];
            }
        } else if ($saveCard) {
            $payload['paymentSession']['recurrence']['action'] = NPG_CONTRACT_CREATION;
            $contractId = $this->generate_contract_id($customerId);
        }

        if ($contractId !== null) {
            $payload['paymentSession']['recurrence']['contractId'] = $contractId;
            $payload['paymentSession']['recurrence']['contractType'] = NPG_CONTRACT_CIT;
        }

        return $payload;
    }

    public function get_customer_one_click_contracts($customerId)
    {
        $ret = ['has_contracts' => false];

        try {
            $response = $this->get_customer_contracts($customerId);

            if (isset($response['contracts']) && is_array($response['contracts']) && count($response['contracts']) > 0) {
                foreach ($response['contracts'] as $contract) {
                    if ($contract['contractType'] == NPG_CONTRACT_CIT) {
                        $ret['contracts'][] = $contract;
                    }
                }
            }

            if (array_key_exists('contracts', $ret)) {
                $ret['has_contracts'] = true;
            }
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Unable to retrive customer related info.', 'woocommerce-gateway-nexi-xpay'));
        }

        return $ret;
    }

    public function get_customer_contracts($customerId)
    {
        try {
            $response = $this->exec_get('contracts/customers/' . $customerId);
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception($exc->getMessage());
        }

        if ($response['status_code'] === 404) {
            throw new \Exception(__('Customer not found.', 'woocommerce-gateway-nexi-xpay'));
        }

        if ($response['status_code'] !== 200) {
            throw new \Exception(__('Unable to retrive customer related info.', 'woocommerce-gateway-nexi-xpay'));
        }

        return $response['response'];
    }

    public function get_order_info($order_id)
    {
        try {
            $npgOrderId = \Nexi\OrderHelper::getOrderMeta($order_id, "_npg_orderId", true);

            if (!$npgOrderId) {
                throw new \Exception(__('NPG orderId not found for order: ', 'woocommerce-gateway-nexi-xpay') . $order_id);
            }

            $response = $this->exec_get('orders/' . $npgOrderId);
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Unable to retrive order related info.', 'woocommerce-gateway-nexi-xpay'));
        }

        if ($response['status_code'] === 404) {
            throw new \Exception(__('Order not found.', 'woocommerce-gateway-nexi-xpay'));
        }

        if ($response['status_code'] !== 200) {
            throw new \Exception(__('Unable to retrive order related info.', 'woocommerce-gateway-nexi-xpay'));
        }

        return $response['response'];
    }

    public function get_order_status($order_id)
    {
        $foundAuthorization = null;

        try {
            $orderInfo = $this->get_order_info($order_id);

            if (!array_key_exists('operations', $orderInfo) || !is_array($orderInfo['operations'])) {
                throw new \Exception('Missing operations for order: ' . $order_id . ' - response: ' . json_encode($orderInfo));
            }

            foreach ($orderInfo["operations"] as $operation) {
                if ($operation["operationType"] == NPG_OT_AUTHORIZATION) {
                    $foundAuthorization = $operation;
                    break;
                }
            }
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());
        }

        return $foundAuthorization;
    }

    public function get_refund_operation_id($operations)
    {
        foreach ($operations as $operation) {
            if (!in_array($operation['operationType'], [NPG_OT_AUTHORIZATION, NPG_OT_CAPTURE])) {
                continue;
            }

            if (in_array($operation['operationResult'], array(NPG_OR_AUTHORIZED, NPG_OR_EXECUTED))) {
                return $operation['operationId'];
            }
        }

        return null;
    }

    public function refund($orderId, $amount)
    {
        try {
            $orderInfo = $this->get_order_info($orderId);

            $operationId = $this->get_refund_operation_id($orderInfo['operations']);

            if ($operationId === null) {
                throw new \Exception('Operation related to order could not be found. Orderinfo: ' . json_encode($orderInfo));
            }

            $currency = $orderInfo['orderStatus']['order']['currency'];

            $payload = [
                'amount' => \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit($amount, $currency),
                'currency' => $currency
            ];

            $extraHeaders = array(
                'Idempotency-Key: ' . self::generate_uuid()
            );

            $response = $this->exec_post('operations/' . $operationId . '/refunds', $payload, $extraHeaders);

            if ($response['status_code'] !== 200) {
                throw new \Exception('Error while proccessing refund request - ' . json_encode(['payload' => $payload, 'response' => $response]));
            }

            return true;
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());
        }

        throw new \Exception(__('Unable to complete refund operation.', 'woocommerce-gateway-nexi-xpay'));
    }

    /**
     * only one accounting operation is allowed
     *
     * @param array $operations
     * @return string|null
     */
    public function get_account_operation_id($operations)
    {
        $op = null;
        $accountingDone = false;

        foreach (array_reverse($operations) as $operation) {
            if (!in_array($operation['operationType'], [NPG_OT_AUTHORIZATION, NPG_OT_CAPTURE])) {
                continue;
            }

            if ($op === null && $operation['operationType'] == NPG_OT_AUTHORIZATION && $operation['operationResult'] == NPG_OR_AUTHORIZED) {
                $op = $operation['operationId'];
            } else if ($operation['operationType'] == NPG_OT_CAPTURE && $operation['operationResult'] == NPG_OR_EXECUTED) {
                $accountingDone = true;
            }
        }

        if ($op !== null && !$accountingDone) {
            return $op;
        }

        return null;
    }

    public function account($orderId, $amount)
    {
        try {
            $orderInfo = $this->get_order_info($orderId);

            $operationId = $this->get_account_operation_id($orderInfo['operations']);

            if ($operationId === null) {
                throw new \Exception('Operation related to order could not be found. Orderinfo: ' . json_encode($orderInfo));
            }

            $currency = $orderInfo['orderStatus']['order']['currency'];

            $payload = [
                'amount' => \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit($amount, $currency),
                'currency' => $currency
            ];

            $extraHeaders = array(
                'Idempotency-Key: ' . self::generate_uuid()
            );

            $response = $this->exec_post('operations/' . $operationId . '/captures', $payload, $extraHeaders);

            if ($response['status_code'] !== 200) {
                throw new \Exception('Unablee to performe capture - ' . json_encode(['payload' => $payload, 'response' => $response]));
            }

            return true;
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());
        }

        throw new \Exception(__('Unable to complete capture operation.', 'woocommerce-gateway-nexi-xpay'));
    }

    public function recurring_payment($order, $contractId, $amount)
    {
        $customerId = $order->get_customer_id();

        $orderId = $this->generate_npg_order_id(18, $order->get_id());

        $payload = [
            "order" => [
                "orderId" => $orderId,
                "invoiceId" => substr("Order-" . $order->get_order_number(), 0, 30),
                "amount" => $amount,
                "currency" => $order->get_currency(),
                "customField" => "WC Order " . $order->get_id(),
                "customerId" => $customerId,
            ],
            "contractId" => $contractId,
            "captureType" => "IMPLICIT",
        ];

        $params = WC_NPG_3DS20_Data_Provider::calculate_params($order);

        if (count($params) > 0) {
            $payload['order']['customerInfo'] = WC_NPG_3DS20_Data_Provider::calculate_params($order);
        }

        try {
            $response = $this->exec_post("orders/mit", $payload);

            if ($response['status_code'] !== 200) {
                throw new \Exception('Error while performing the recurring payment - ' . json_encode($response));
            }

            if (!isset($response['response']['operation'])) {
                throw new \Exception('Invalid response, "operation" not found - ' . json_encode($response));
            }

            $operation = $response['response']['operation'];

            if (in_array($operation['operationResult'], NPG_PAYMENT_SUCCESSFUL)) {
                return $orderId;
            } else if (in_array($operation['operationResult'], NPG_PAYMENT_FAILURE)) {
                throw new \Exception('Payment failed - ' . json_encode($response));
            } else {
                throw new \Exception('Invalid operationResult - ' . json_encode($response));
            }
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Unable to perform the recurring payment.', 'woocommerce-gateway-nexi-xpay'));
        }
    }

    public function deactivate_contract($contractId)
    {
        try {
            $url = 'contracts/' . $contractId . '/deactivation';

            $response = $this->exec_post($url, []);

            if ($response['status_code'] !== 200) {
                throw new \Exception('Error deactivation contract ' . $contractId . ' - response: ' . json_encode($response));
            }

            return true;
        } catch (\Exception $exc) {

            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());
        }

        return false;
    }

    public function build_payment($total, $wc, $recurringPayment)
    {
        try {
            $amount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit($total, get_woocommerce_currency());

            $orderId = $wc->session->get('npg_build_order_id');

            if ($orderId === null) {
                $orderId = $this->generate_npg_order_id(18);

                $wc->session->set('npg_build_order_id', $orderId);
            }

            $customerId = $wc->customer->get_id();

            $payload = array(
                "merchantUrl" => get_site_url(),
                "order" => array(
                    "orderId" => $orderId,
                    "invoiceId" => substr("Order-" . $orderId, 0, 30),
                    "amount" => $amount,
                    "currency" => get_woocommerce_currency(),
                    "description" => "WC Order ",
                    "customField" => "Woocommerce " . WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION . " - nexixpay " . WC_GATEWAY_XPAY_VERSION,
                    "customerId" => $customerId,
                ),
                "paymentSession" => array(
                    "actionType" => "PAY",
                    "amount" => $amount,
                    "recurrence" => array(
                        "action" => NPG_NO_RECURRING
                    ),
                    "exemptions" => "NO_PREFERENCE",
                    "language" => WC_Gateway_XPay_Generic_Method::get_language_id(),
                    "resultUrl" => $this->get_result_url($orderId),
                    "cancelUrl" => $this->get_cancel_url($orderId),
                    "notificationUrl" => get_rest_url(null, "woocommerce-gateway-nexi-xpay/s2s/npg/" . $orderId),
                    "paymentService" => 'CARDS'
                ),
                "version" => 3,
            );

            if ($recurringPayment) {
                if (!$this->nexi_xpay_recurring_enabled) {
                    Log::actionWarning(__FUNCTION__ . ": recurring payment for non recurring payment method");
                    throw new \Exception("Recurring not enabled");
                }

                $payload = $this->get_recurring_params($payload, $customerId);
            }

            if (isset($wc->customer) && $this->nexi_xpay_3ds20_enabled) {
                $dati3ds = WC_NPG_3DS20_Data_Provider::getParamsFromWC($wc);

                if (!empty($dati3ds)) {
                    $payload["order"]["customerInfo"] = $dati3ds;
                }
            }

            $response = $this->exec_post("orders/build", $payload);

            \Nexi\Log::actionDebug("DEBUG: " . json_encode($response));

            if ($response['status_code'] !== 200) {
                throw new \Exception('Error while initializing the payment - ' . json_encode($response));
            }

            \Nexi\OrderHelper::updateOrderMeta($orderId, "_npg_is_build", true);
            \Nexi\OrderHelper::updateOrderMeta($orderId, "_npg_orderId", $orderId);
            \Nexi\OrderHelper::updateOrderMeta($orderId, "_npg_securityToken", $response['response']['securityToken']);
            \Nexi\OrderHelper::updateOrderMeta($orderId, "_npg_sessionId", $response['response']['sessionId']);

            if ($recurringPayment) {
                \Nexi\OrderHelper::updateOrderMeta($orderId, "_npg_recurringContractId", $payload['paymentSession']['recurrence']['contractId']);
            }

            return $response['response'];
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Unable to initialize the payment.', 'woocommerce-gateway-nexi-xpay'));
        }
    }

    public function build_payment_finalize($wc, $sessionId)
    {
        try {
            $payload = [
                'sessionId' => $sessionId
            ];

            $response = $this->exec_post("build/finalize_payment", $payload);

            $wc->session->__unset('npg_build_order_id');

            if ($response['status_code'] !== 200) {
                throw new \Exception('Error while finalizing the payment - ' . json_encode($response));
            }

            return $response['response'];
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Unable to finalize the payment.', 'woocommerce-gateway-nexi-xpay'));
        }
    }

    public function build_state($sessionId)
    {
        try {
            $payload = [
                'sessionId' => $sessionId
            ];

            $response = $this->exec_get("build/state", $payload);

            if ($response['status_code'] !== 200) {
                return null;
            }

            return $response['response'];
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            return null;
        }
    }

    public function google_pay_payment($order, $googlePayJson)
    {
        try {
            $amount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit($order->get_total(), $order->get_currency());

            $orderId = $this->generate_npg_order_id(18, $order->get_order_number());

            $customerId = $order->get_customer_id();

            $payload = array(
                "order" => array(
                    "orderId" => $orderId,
                    "invoiceId" => substr("Order-" . $order->get_order_number(), 0, 30),
                    "amount" => $amount,
                    "currency" => $order->get_currency(),
                    "description" => "WC Order " . $order->get_id(),
                    "customField" => "Woocommerce " . WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION . " - nexixpay " . WC_GATEWAY_XPAY_VERSION,
                ),
                "paymentSession" => array(
                    "actionType" => "PAY",
                    "amount" => $amount,
                    "recurrence" => array(
                        "action" => NPG_NO_RECURRING
                    ),
                    "exemptions" => "NO_PREFERENCE",
                    "language" => WC_Gateway_XPay_Generic_Method::get_language_id(),
                    "resultUrl" => $this->get_result_url($order->get_id()),
                    "cancelUrl" => $this->get_cancel_url($order->get_id()),
                    "notificationUrl" => get_rest_url(null, "woocommerce-gateway-nexi-xpay/s2s/npg/" . $order->get_id()),
                ),
                "googlePayPaymentData" => json_decode($googlePayJson, true),
            );

            if (is_user_logged_in() && $customerId) {
                $payload["order"]["customerId"] = $customerId;
            }

            if ($this->nexi_xpay_3ds20_enabled) {
                $payload['order']['customerInfo'] = WC_NPG_3DS20_Data_Provider::calculate_params($order);
            }

            $response = $this->exec_post("orders/googlepay", $payload);

            if ($response['status_code'] !== 200) {
                throw new \Exception('Error while initializing the payment - ' . json_encode($response));
            }

            \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_npg_securityToken", "noCheck");
            \Nexi\OrderHelper::updateOrderMeta($order->get_id(), "_npg_orderId", $orderId);

            return $response['response'];
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            throw new \Exception(__('Unable to initialize the payment.', 'woocommerce-gateway-nexi-xpay'));
        }
    }

    /**
     * GET request
     *
     * @param string $url
     * @param array $payload
     * @param array $extraHeaders
     * @return array
     */
    private function exec_get($url, $payload = [], $extraHeaders = [])
    {
        return $this->exec_rest_curl('GET', $url, $payload, $extraHeaders);
    }

    /**
     * POST request
     *
     * @param string $url
     * @param array $payload
     * @param array $extraHeaders
     * @return array
     */
    private function exec_post($url, $payload, $extraHeaders = [])
    {
        return $this->exec_rest_curl('POST', $url, $payload, $extraHeaders);
    }

    /**
     * executes curl request and returns an array with the response and the status code
     *
     * @param string $method
     * @param string $url
     * @param array $payload
     * @param array $extraHeaders
     * @return array
     */
    public function exec_rest_curl($method, $url, $payload = [], $extraHeaders = [])
    {
        $connection = curl_init();

        if (!$connection) {
            throw new \Exception('Curl connection error');
        }

        $requestUrl = $this->base_url . '/api/phoenix-0.0/psp/api/v1/' . $url;

        if ($method === "GET" && \count($payload) > 0) {
            $requestUrl = \sprintf("%s?%s", $requestUrl, http_build_query($payload));
        } else if ($method === "POST") {
            curl_setopt($connection, CURLOPT_POST, 1);

            curl_setopt($connection, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $httpHeader = [
            'x-api-key: ' . $this->nexi_npg_api_key,
            'x-plugin-name: Woocommerce ' . WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION . ' - nexixpay ' . WC_GATEWAY_XPAY_VERSION,
            'Correlation-Id: ' . self::generate_uuid(),
            'Content-Type: application/json'
        ];

        $options = [
            CURLOPT_URL => $requestUrl,
            CURLOPT_HTTPHEADER => array_merge($httpHeader, $extraHeaders),
            CURLOPT_RETURNTRANSFER => 1,
            CURLINFO_HEADER_OUT => true
        ];

        Log::actionInfo(__FUNCTION__ . ' - Request : ' . json_encode([
            'method' => $method,
            'requestUrl' => $requestUrl,
            'httpHeader' => array_splice($httpHeader, 1),
            'payload' => $payload,
            'extraHeaders' => $extraHeaders
        ]));

        curl_setopt_array($connection, $options);

        $response = curl_exec($connection);

        if ($response === false) {
            Log::actionWarning(
                __FUNCTION__ . ': Curl connection error - ' . json_encode(
                    [
                        'url' => $requestUrl,
                        'pay_load' => $payload,
                        'response' => $response
                    ]
                )
            );

            throw new \Exception(curl_error($connection));
        }

        $curlInfo = curl_getinfo($connection);

        curl_close($connection);

        $json = json_decode($response, true);

        if ($curlInfo['http_code'] == 200 || $curlInfo['http_code'] == 500) {
            if (!(is_array($json) && json_last_error() === JSON_ERROR_NONE)) {
                Log::actionWarning(
                    __FUNCTION__ . ': Curl - JSON error - ' . json_encode(
                        [
                            'url' => $requestUrl,
                            'pay_load' => $payload,
                            'response' => $response,
                            'status' => $curlInfo['http_code']
                        ]
                    )
                );

                throw new \Exception('JSON error');
            }
        }

        Log::actionInfo(__FUNCTION__ . ' - Response : ' . json_encode([
            'status_code' => $curlInfo['http_code'],
            'response' => $json
        ]));

        return [
            'status_code' => $curlInfo['http_code'],
            'response' => $json
        ];
    }

    /**
     * generates a uuid
     *
     * @return string
     */
    public static function generate_uuid()
    {
        $uuid = substr(bin2hex(random_bytes(32)), 0, 32);

        return implode("-", [substr($uuid, 0, 8), substr($uuid, 8, 4), substr($uuid, 12, 4), substr($uuid, 16, 4), substr($uuid, 20, 12)]);
    }

    private function generate_npg_order_id($length = 18, $prefix = null)
    {
        $id = '';

        if ($prefix !== null) {
            $id .= $prefix . '-';
        }

        if ($length > 10) {
            $id .= time();
        }

        $id .= (new \DateTime())->format('uvsB');

        while (strlen($id) < $length) {
            $id .= (int) ((rand() * rand()) / rand());
        }

        return substr($id, 0, $length);
    }

    private function generate_contract_id($customerId, $prefix = '')
    {
        return substr(md5($prefix . $customerId . '-' . time()), 0, 18);
    }

}
