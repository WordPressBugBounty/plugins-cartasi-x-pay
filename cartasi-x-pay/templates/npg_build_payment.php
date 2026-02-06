<input type="hidden" id="xpay_admin_url" value="<?php echo admin_url() ?>">

<div class="loader-container">
    <p class="loading"></p>
</div>

<input type="hidden" id="validation-error"
    value="<?php echo __('Incorrect or missing data', 'woocommerce-gateway-nexi-xpay'); ?>">
<input type="hidden" id="session-error"
    value="<?php echo __('XPay Build session expired', 'woocommerce-gateway-nexi-xpay'); ?>">

<fieldset id="card-fieldset-build" class="form--fieldset">
    <div class="form--input__row">
        <div class="form--wrap__row">
            <div class="form--input__wrap col-10" id="CARD_NUMBER"></div>
        </div>
    </div>

    <div class="form--input__row">
        <div class="form--wrap__row">
            <div class="form--input__wrap col-5" id="EXPIRATION_DATE"></div>
            <div class="form--input__wrap col-5" id="SECURITY_CODE"></div>
        </div>
    </div>

    <div class="form--input__row">
        <div class="form--wrap__row">
            <div class="form--input__wrap" id="CARDHOLDER_NAME"></div>
            <div class="form--input__wrap" id="CARDHOLDER_SURNAME"></div>
        </div>
    </div>

    <div id="nexi-xpay-extra-fields-container"></div>
</fieldset>

<div class="npg-build-error-msg-container"></div>

<input type="hidden" id="reload-npg-build">

<script>
    jQuery(function ($) {
        $(document).ready(function () {
            $("#reload-npg-build").trigger("change");
        });
    });
</script>