var configuration;

jQuery(document).ready(function () {
    jQuery("form.checkout").on("change", 'input[name="payment_method"]', function () {
        if (jQuery('input[name="payment_method"]:checked').val() === "xpay_applepay_button") {
            loadApplePayConfiguration();

            jQuery("#place_order").hide();
        }
    });
});

function loadApplePayConfiguration() {
    jQuery.ajax({
        type: "POST",
        data: {
            action: "apple_pay_configuration",
        },
        url: `${jQuery("#xpay_admin_url").val()}admin-ajax.php`,
        beforeSend: function () {
            jQuery.blockUI({ message: "" });
        },
        success: function (response) {
            jQuery.unblockUI();

            configuration = response;

            jQuery("#applepay-button").attr("buttonstyle", response.button_style);
            jQuery("#applepay-button").attr("type", response.button_type);
            jQuery("#applepay-button").attr("locale", response.button_locale);
        },
        complete: function () {
            jQuery.unblockUI();
        },
    });
}

function onApplePayButtonClicked() {
    const request = {
        countryCode: configuration.transactionInfo.countryCode,
        currencyCode: configuration.transactionInfo.currencyCode,
        supportedNetworks: configuration.cards,
        merchantCapabilities: ["supports3DS"],
        total: {
            label: configuration.config.merchantLabel,
            amount: configuration.transactionInfo.totalAmount,
        },
    };

    const session = new ApplePaySession(14, request);

    session.onvalidatemerchant = function (event) {
        jQuery.ajax({
            type: "POST",
            data: {
                action: "apple_pay_validate_merchant",
                validation_url: event.validationURL,
            },
            url: `${jQuery("#xpay_admin_url").val()}admin-ajax.php`,
            success: function (merchantSession) {
                session.completeMerchantValidation(merchantSession);
            },
        });
    };

    session.onpaymentauthorized = function (event) {
        jQuery("#applePayJson").val(JSON.stringify(event.payment));

        session.completePayment({ status: 0 });

        jQuery("#place_order").trigger("click");
    };

    session.begin();
}
