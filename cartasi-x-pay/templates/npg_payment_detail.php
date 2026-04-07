<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="order_xpay_details" class="panel">
    <?php if (isset($orderError)) { ?>
        <h3><?php echo esc_html($orderError); ?></h3>
    <?php } else { ?>
        <?php
        $firstOp = null;
        $customerName = null;
        $customerEmail = null;

        if ($showOperations) {
            $firstOp = $orderInfo['operations'][0];

            if (array_key_exists('customerInfo', $firstOp) && count($firstOp['customerInfo']) > 0) {
                if (array_key_exists('cardHolderName', $firstOp['customerInfo']) && $firstOp['customerInfo']['cardHolderName']) {
                    $customerName = $firstOp['customerInfo']['cardHolderName'];
                }

                if (array_key_exists('cardHolderEmail', $firstOp['customerInfo']) && $firstOp['customerInfo']['cardHolderEmail']) {
                    $customerEmail = $firstOp['customerInfo']['cardHolderEmail'];
                }
            }
        } else if (array_key_exists('orderStatus', $orderInfo) && array_key_exists('order', $orderInfo['orderStatus']) && count($orderInfo['orderStatus']['order']) > 0) {
            if (array_key_exists('customerInfo', $orderInfo['orderStatus']['order']) && array_key_exists('cardHolderName', $orderInfo['orderStatus']['order']['customerInfo'])) {
                $customerName = $orderInfo['orderStatus']['order']['customerInfo']['cardHolderName'];
            }

            if (array_key_exists('customerInfo', $orderInfo['orderStatus']['order']) && array_key_exists('cardHolderEmail', $orderInfo['orderStatus']['order']['customerInfo'])) {
                $customerEmail = $orderInfo['orderStatus']['order']['customerInfo']['cardHolderEmail'];
            }
        }
        ?>

        <div class="order_data_column_container">
            <?php if ($customerName !== null || $customerEmail !== null) { ?>
                <div class="order_data_column">
                    <h3><?php echo esc_html__("Cardholder", 'woocommerce-gateway-nexi-xpay'); ?></h3>
                    <p>
                        <?php if ($customerName !== null) { ?>
                            <strong><?php echo esc_html__("Name: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($customerName); ?> <br>
                        <?php } ?>

                        <?php if ($customerEmail !== null) { ?>
                            <strong><?php echo esc_html__("Mail: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($customerEmail); ?> <br>
                        <?php } ?>
                    </p>
                </div>
            <?php } ?>

            <?php if ($firstOp !== null && (array_key_exists('paymentCircuit', $firstOp) || (array_key_exists('paymentInstrumentInfo', $firstOp) && strlen(trim($firstOp['paymentInstrumentInfo'])) > 0))) { ?>
                <div class="order_data_column">
                    <h3><?php echo esc_html__("Card detail", 'woocommerce-gateway-nexi-xpay'); ?></h3>
                    <p>
                        <?php if ($firstOp['paymentCircuit'] != '') { ?>
                            <strong><?php echo esc_html__("Card: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($firstOp['paymentCircuit']); ?> <br>
                        <?php } ?>
                        <?php if ($firstOp['paymentInstrumentInfo'] && strlen(trim($firstOp['paymentInstrumentInfo'])) > 0) { ?>
                            <strong><?php echo esc_html__("Card pan: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($firstOp['paymentInstrumentInfo']); ?> <br>
                        <?php } ?>
                    </p>
                </div>
            <?php } ?>

            <?php
            if (
                ($firstOp !== null && array_key_exists('operationTime', $firstOp)) ||
                (array_key_exists('orderStatus', $orderInfo) && array_key_exists('order', $orderInfo['orderStatus']) && count($orderInfo['orderStatus']['order']) > 0)
            ) {
                ?>
                <div class="order_data_column">
                    <h3><?php echo esc_html__("Transaction detail", 'woocommerce-gateway-nexi-xpay'); ?></h3>
                    <p>
                        <?php
                        if ($firstOp !== null && $firstOp['operationTime'] != '') {
                            $transactionDate = new \DateTime($firstOp['operationTime']);

                            if ($transactionDate) {
                                ?>
                                <strong><?php echo esc_html__("Date: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($transactionDate->format("d/m/Y H:i")); ?><br>
                                <?php
                            }
                        }
                        ?>

                        <?php if (isset($installmentsNumber) && $installmentsNumber >= 2) { ?>
                            <strong><?php echo esc_html__("Installments: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($installmentsNumber); ?><br>
                        <?php } ?>

                        <?php if (array_key_exists('orderStatus', $orderInfo) && array_key_exists('order', $orderInfo['orderStatus']) && count($orderInfo['orderStatus']['order']) > 0) { ?>
                            <?php if ($orderInfo['orderStatus']['order']['amount']) { ?>
                                <strong><?php echo esc_html__("Amount: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html(\Nexi\WC_Gateway_NPG_Currency::format_npg_amount($orderInfo['orderStatus']['order']['amount'], $orderInfo['orderStatus']['order']['currency'])); ?> <?php echo esc_html($currencySign); ?><br>
                            <?php } ?>
                            <?php if ($orderInfo['orderStatus']['order']['orderId'] != '') { ?>
                                <strong> <?php echo esc_html__("Order ID: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($orderInfo['orderStatus']['order']['orderId']); ?><br>
                            <?php } ?>
                        <?php } ?>
                    </p>
                </div>
            <?php } ?>
        </div>

        <?php if ($showOperations || $canAccount) {
            ?>
            <?php if ($showOperations) { ?>
                <h3><?php echo esc_html__("Accounting operations", 'woocommerce-gateway-nexi-xpay'); ?></h3>
            <?php } else if ($canAccount) { ?>
                <h3><?php echo esc_html__("New accounting operation", 'woocommerce-gateway-nexi-xpay'); ?></h3>
            <?php } ?>
            <div class="woocommerce_subscriptions_related_orders operation-detail-container">
                <?php if ($showOperations) { ?>
                    <div class="operation-detail-table">
                        <table class="wp-list-table widefat fixed striped table-view-list posts">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__("Type of operation", 'woocommerce-gateway-nexi-xpay'); ?></th>
                                    <th><?php echo esc_html__("Result", 'woocommerce-gateway-nexi-xpay'); ?></th>
                                    <th><?php echo esc_html__("Amount", 'woocommerce-gateway-nexi-xpay'); ?></th>
                                    <th><?php echo esc_html__("Date", 'woocommerce-gateway-nexi-xpay'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderInfo['operations'] as $operation) { ?>
                                    <tr>

                                        <td><?php echo esc_html($operation['operationType']) ?></td>
                                        <td><?php echo esc_html($operation['operationResult']) ?></td>
                                        <td><?php echo esc_html(\Nexi\WC_Gateway_NPG_Currency::format_npg_amount($operation['operationAmount'], $currency) . ' ' . $this->get_npg_currency_sign($currency)) ?></td>
                                        <td>
                                            <?php
                                            $oData = new \DateTime($operation['operationTime']);
                                            echo esc_html($oData->format("d/m/Y H:i"));
                                            ?>
                                        </td>

                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
                <?php if ($canAccount) { ?>
                    <div class="accounting-container">
                        <div class="accounting-action-container">
                            <div class="input-group">
                                <?php
                                $maxAmount = null;

                                if (array_key_exists('orderStatus', $orderInfo) && array_key_exists('order', $orderInfo['orderStatus']) && count($orderInfo['orderStatus']['order']) > 0 && $orderInfo['orderStatus']['order']['amount']) {
                                    $maxAmount = $orderInfo['orderStatus']['order']['amount'];
                                }
                                ?>

                                <input class="wc_input_price" id="xpay_account_account_input" value="" type="number" min="0.00" <?php if ($maxAmount !== null) { ?> max="<?php echo esc_attr(\Nexi\WC_Gateway_NPG_Currency::format_npg_amount($maxAmount, $currency)); ?>" <?php } ?> step="0.01">
                                <div class="input-currency">
                                    <?php
                                echo esc_html($currencySign);
                                    ?>
                                </div>
                            </div>

                            <input type="hidden" id="xpay_account_form_api_url" value="<?php echo esc_url($accountUrl . ''); ?>">
                            <input type="hidden" id="xpay_account_form_currency_label" value="<?php echo esc_attr($currencyLabel . ''); ?>">
                            <input type="hidden" id="xpay_account_form_question" value="<?php echo esc_attr(__('Do you confirm to capture', 'woocommerce-gateway-nexi-xpay')); ?>">
                            <input type="hidden" id="xpay_account_form_success_message" value="<?php echo esc_attr(__("Capture successful", 'woocommerce-gateway-nexi-xpay')) . ''; ?>">

                            <button type="button" id="xpay_account_form_btn" class="button button-primary accounting-btn">
                                <?php echo esc_html__("Capture", 'woocommerce-gateway-nexi-xpay'); ?>
                            </button>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    <?php } ?>
</div>
