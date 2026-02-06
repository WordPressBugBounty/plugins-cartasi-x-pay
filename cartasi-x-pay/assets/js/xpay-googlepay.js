const baseRequest = {
    apiVersion: 2,
    apiVersionMinor: 0,
};

let paymentsClient;

function getBaseCardPaymentMethod(configuration) {
    return {
        type: "CARD",
        parameters: {
            allowedAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
            allowedCardNetworks: configuration.cards,
        },
    };
}

function processPayment(paymentData) {
    jQuery("#googlePayJson").val(JSON.stringify(paymentData));

    jQuery("#place_order").trigger("click");
}

function onGooglePaymentButtonClicked(configuration) {
    const admin_url = jQuery("#xpay_admin_url").val();

    jQuery.ajax({
        type: "POST",
        data: jQuery('form.checkout.woocommerce-checkout').serialize(),
        url: `${admin_url}admin-ajax.php?action=validate_checkout_form`,
        beforeSend: function () {
            jQuery.blockUI({message: ""});
        },
        success: function (response) {
            jQuery.unblockUI();

            if (response.errors.length === 0) {
                const paymentDataRequest = getGooglePaymentDataRequest(configuration);

                paymentsClient
                        .loadPaymentData(paymentDataRequest)
                        .then(function (paymentData) {
                            processPayment(paymentData);
                        })
                        .catch(function (err) {
                            console.error(err);
                        });
            }
        },
        complete: function () {
            jQuery.unblockUI();
        },
    });
}

function addGooglePayButton(configuration) {
    const button = paymentsClient.createButton({
        onClick: function () {
            onGooglePaymentButtonClicked(configuration);
        },
        buttonSizeMode: "fill",
        buttonColor: configuration.config.button_color,
        buttonType: configuration.config.button_type,
        buttonLocale: configuration.config.button_locale,
    });

    const buttonContainer = document.getElementById("googlepay-button-container");

    if (buttonContainer) {
        buttonContainer.innerHTML = "";

        buttonContainer.appendChild(button);
    }
}

function getGooglePaymentDataRequest(configuration) {
    const paymentDataRequest = Object.assign({emailRequired: true}, baseRequest);

    paymentDataRequest.allowedPaymentMethods = [
        Object.assign({}, getBaseCardPaymentMethod(configuration), {
            tokenizationSpecification: {
                type: "PAYMENT_GATEWAY",
                parameters: {
                    gateway: configuration.config.gateway,
                    gatewayMerchantId: configuration.config.gateway_merchant_id,
                },
            },
        }),
    ];

    paymentDataRequest.transactionInfo = {
        countryCode: configuration.transactionInfo.countryCode,
        currencyCode: configuration.transactionInfo.currencyCode,
        totalPriceStatus: "FINAL",
        totalPrice: configuration.transactionInfo.totalPrice,
    };

    if (configuration.config.test_mode) {
        paymentDataRequest.merchantInfo = {
            merchantName: configuration.config.merchant_name,
        };
    } else {
        paymentDataRequest.merchantInfo = {
            merchantId: configuration.config.merchant_id,
            merchantName: configuration.config.merchant_name,
        };
    }

    return paymentDataRequest;
}

function prefetchGooglePaymentData(configuration) {
    const paymentDataRequest = getGooglePaymentDataRequest(configuration);

    paymentDataRequest.transactionInfo = {
        countryCode: configuration.transactionInfo.countryCode,
        currencyCode: configuration.transactionInfo.currencyCode,
        totalPriceStatus: "FINAL",
        totalPrice: configuration.transactionInfo.totalPrice,
    };

    paymentsClient.prefetchPaymentData(paymentDataRequest);
}

function loadGooglePayButton() {
    var admin_url = jQuery("#xpay_admin_url").val();

    jQuery.ajax({
        type: "POST",
        data: {
            action: "google_pay_configuration",
        },
        url: `${admin_url}admin-ajax.php`,
        beforeSend: function () {
            jQuery.blockUI({message: ""});
        },
        success: function (configuration) {
            jQuery.unblockUI();

            if (configuration.config.test_mode) {
                paymentsClient = new google.payments.api.PaymentsClient({
                    environment: "TEST",
                });
            } else {
                paymentsClient = new google.payments.api.PaymentsClient({
                    environment: "PRODUCTION",
                });
            }

            const isReadyToPayRequest = Object.assign({}, baseRequest, {
                allowedPaymentMethods: [getBaseCardPaymentMethod(configuration)],
            });

            paymentsClient
                    .isReadyToPay(isReadyToPayRequest)
                    .then(function (response) {
                        jQuery.unblockUI();

                        if (response.result) {
                            addGooglePayButton(configuration);
                            // @todo prefetch payment data to improve performance after confirming site functionality
                            prefetchGooglePaymentData(configuration);
                        }
                    })
                    .catch(function (err) {
                        jQuery.unblockUI();

                        console.error(err);
                    });
        },
        complete: function () {
            jQuery.unblockUI();
        },
    });
}

jQuery(document).ready(function () {
    jQuery("form.checkout").on("change", 'input[name="payment_method"]', function () {
        if (jQuery('input[name="payment_method"]:checked').val() === "xpay_googlepay_button") {
            loadGooglePayButton();

            jQuery("#place_order").hide();
        } else {
            jQuery("#place_order").show();
        }
    });
});

jQuery(document).on('update_checkout', function () {
    setTimeout(function () {
        if (jQuery('input[name="payment_method"]:checked').val() === "xpay_googlepay_button") {
            loadGooglePayButton();
        }
    }, 500);
});
