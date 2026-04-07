<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="order_xpay_details" class="panel">
    <div class="order_data_column_container">
        <div class="order_data_column">
            <h3><?php echo esc_html__("Cardholder", 'woocommerce-gateway-nexi-xpay'); ?></h3>
            <p>
                <?php if ($custumerDisplayName != "") { ?>
                    <strong><?php echo esc_html__("Name: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($custumerDisplayName); ?> <br>
                <?php } ?>

                <?php if ($custumerEmail != '') { ?>
                    <strong><?php echo esc_html__("Mail: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($custumerEmail); ?> <br>
                <?php } ?>
            </p>
        </div>

        <div class="order_data_column">
            <h3><?php echo esc_html__("Card detail", 'woocommerce-gateway-nexi-xpay'); ?></h3>
            <p>
                <?php if ($paymentCardBrand != '') { ?>
                    <strong><?php echo esc_html__("Card: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($paymentCardBrand); ?> <br>
                <?php } ?>
                <?php if ($paymentCardBrandNazionalita != '') { ?>
                    <strong><?php echo esc_html__("Nationality: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($paymentCardBrandNazionalita); ?> <br>
                <?php } ?>
                <?php if ($paymentCardBrandPan != '') { ?>
                    <strong><?php echo esc_html__("Card pan: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($paymentCardBrandPan); ?> <br>
                <?php } ?>
                <?php if ($paymentCardBrandExpiration != '') { ?>
                    <strong><?php echo esc_html__("Expiry date: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($paymentCardBrandExpiration); ?> <br>
                <?php } ?>
            </p>
        </div>

        <div class="order_data_column">
            <h3><?php echo esc_html__("Transaction detail", 'woocommerce-gateway-nexi-xpay'); ?></h3>
            <p>
                <?php if ($transactionDate != '') { ?>
                    <strong><?php echo esc_html__("Date: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($transactionDate); ?><br>
                <?php } ?>
                <?php if ($transactionValue) { ?>
                    <strong><?php echo esc_html__("Amount: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html(number_format($transactionValue, 2, ',', '.')); ?> <?php echo wp_kses_post($currencySign); ?><br>
                <?php } ?>
                <?php if ($transactionCodTrans != '') { ?>
                    <strong> <?php echo esc_html__("Transaction code: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($transactionCodTrans); ?><br>
                <?php } ?>
                <?php if ($transactionNumContratto != '') {
                ?>
                    <strong> <?php echo esc_html__("Contract number: ", 'woocommerce-gateway-nexi-xpay'); ?></strong> <?php echo esc_html($transactionNumContratto); ?><br>
                <?php } ?>
                <?php if ($transactionStatus != '') { ?>
                    <strong><?php echo esc_html__("Status: ", 'woocommerce-gateway-nexi-xpay'); ?> </strong><?php echo esc_html($transactionStatus); ?><br>
                <?php } ?>
            </p>
        </div>
    </div>

    <?php
    $showOperations = is_array($operazioni) && count($operazioni) > 0;
    if ($showOperations || $canAccount) {
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
                                <th><?php echo esc_html__("Date", 'woocommerce-gateway-nexi-xpay'); ?></th>
                                <th><?php echo esc_html__("Type of operation", 'woocommerce-gateway-nexi-xpay'); ?></th>
                                <th><?php echo esc_html__("Amount", 'woocommerce-gateway-nexi-xpay'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operazioni as $operazione) {
                            ?>
                                <tr>
                                    <td>
                                        <?php
                                        $oData = new \DateTime($operazione['dataOperazione']);
                                        echo esc_html($oData->format("d/m/Y H:i"));
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($operazione['tipoOperazione']) ?></td>
                                    <td><?php echo esc_html(number_format(\Nexi\WC_Nexi_Helper::div_bcdiv($operazione['importo'], 100), 2, ",", ".") . ' ' . $this->get_currency_sign($operazione['divisa'])) ?></td>
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
                            <input class="wc_input_price" id="xpay_account_account_input" value="" type="number" min="0.00" max="<?php echo esc_attr(number_format($transactionValue, 2, ".", "")); ?>" step="0.01">
                            <div class="input-currency">
                                <?php
                                echo wp_kses_post($currencySign);
                                ?>
                            </div>
                        </div>

                        <input type="hidden" id="xpay_account_form_api_url" value="<?php echo esc_url($accountUrl . ''); ?>">
                        <input type="hidden" id="xpay_account_form_currency_label" value="<?php echo esc_attr($currencyLabel . ''); ?>">
                        <input type="hidden" id="xpay_account_form_question" value="<?php echo esc_attr(__('Do you confirm to capture', 'woocommerce-gateway-nexi-xpay')); ?>">
                        <?php // translators: %s: transaction code. ?>
                        <input type="hidden" id="xpay_account_form_success_message" value="<?php echo esc_attr(sprintf(__("Capture of transaction %s successful", 'woocommerce-gateway-nexi-xpay'), $transactionCodTrans)); ?>">

                        <button type="button" id="xpay_account_form_btn" class="button button-primary accounting-btn">
                            <?php echo esc_html__("Capture", 'woocommerce-gateway-nexi-xpay'); ?>
                        </button>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
