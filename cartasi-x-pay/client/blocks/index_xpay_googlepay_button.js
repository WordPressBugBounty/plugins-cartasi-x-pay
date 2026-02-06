import React from "react";

import $ from "jquery";

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";

const getPaymentMethodConfiguration = () => {
    const serverData = wc?.wcSettings?.getSetting("xpay_googlepay_button_data", null);

    if (!serverData) {
        throw new Error("xpay_googlepay_button initialization data is not available");
    }

    return serverData;
};

const canMakePayment = () => {
    return getPaymentMethodConfiguration()?.can_make_payment ?? false;
};

const getCreditCardIcons = () => {
    return Object.entries(getPaymentMethodConfiguration()?.icons ?? []).map(
        ([id, { src, alt }]) => {
            return {
                id,
                src,
                alt,
            };
        },
    );
};

const getContentIcons = () => {
    return Object.entries(getPaymentMethodConfiguration()?.content_icons ?? []).map(
        ([id, { src, alt }]) => {
            return {
                id,
                src,
                alt,
            };
        },
    );
};

const getContent = () => {
    return getPaymentMethodConfiguration()?.content ?? "";
};

const getLabel = () => {
    return getPaymentMethodConfiguration()?.label ?? "";
};

const getFeatures = () => {
    return getPaymentMethodConfiguration()?.features ?? [];
};

const getAdminUrl = () => {
    return getPaymentMethodConfiguration()?.admin_url ?? "";
};

const CreditCardLabel = ({ label, icons, components }) => {
    const { PaymentMethodLabel, PaymentMethodIcons } = components;

    return (
        <>
            <PaymentMethodLabel text={label} />

            {PaymentMethodIcons && icons.length > 0 && (
                <PaymentMethodIcons icons={icons} align="right" />
            )}
        </>
    );
};

const baseRequest = {
    apiVersion: 2,
    apiVersionMinor: 0,
};

function getBaseCardPaymentMethod(configuration) {
    return {
        type: "CARD",
        parameters: {
            allowedAuthMethods: ["PAN_ONLY", "CRYPTOGRAM_3DS"],
            allowedCardNetworks: configuration.cards,
        },
    };
}

const CreditCardContent = ({ eventRegistration, emitResponse, onSubmit, components }) => {
    const { PaymentMethodIcons } = components;

    const contentIcons = getContentIcons();

    const { onPaymentSetup, onShippingRateSelectSuccess } = eventRegistration;

    React.useEffect(() => {
        const unsubscribe = onShippingRateSelectSuccess(() => {
            loadGooglePayButton();

            return true;
        });
        return unsubscribe;
    }, [onShippingRateSelectSuccess]);

    React.useEffect(() => {
        $(".wc-block-components-checkout-place-order-button").hide();

        return () => {
            $(".wc-block-components-checkout-place-order-button").show();
        };
    }, []);

    const paymentsClient = React.useRef(null);

    const { validationStore } = window.wc.wcBlocksData;

    const store = useSelect((select) => {
        return select(validationStore);
    });

    const onGooglePayButtonClicked = React.useCallback(
        (configuration) => {
            const hasValidationErrors = store.hasValidationErrors();

            if (!hasValidationErrors) {
                const paymentDataRequest = getGooglePaymentDataRequest(configuration);

                onSubmit();

                paymentsClient.current
                    .loadPaymentData(paymentDataRequest)
                    .then(function (paymentData) {
                        const event = new CustomEvent("xpay_google_pay_update", {
                            detail: {
                                status: "OK",
                                paymentData: paymentData,
                            },
                        });

                        window.dispatchEvent(event);
                    })
                    .catch(function (err) {
                        console.error(err);

                        const strError = "" + err;

                        if (strError.includes("User closed the Payment Request UI")) {
                            const event = new CustomEvent("xpay_google_pay_update", {
                                detail: { status: "KO", user_abort_ui: true },
                            });

                            window.dispatchEvent(event);
                        } else {
                            const event = new CustomEvent("xpay_google_pay_update", {
                                detail: { status: "KO" },
                            });

                            window.dispatchEvent(event);
                        }
                    });
            }
        },
        [store],
    );

    const addGooglePayButton = React.useCallback((configuration) => {
        const button = paymentsClient.current.createButton({
            onClick: function () {
                onGooglePayButtonClicked(configuration);
            },
            buttonSizeMode: "fill",
            buttonColor: configuration.config.button_color,
            buttonType: configuration.config.button_type,
            buttonLocale: configuration.config.button_locale,
        });

        const buttonContainer = document.getElementById("googlepay-button-container");

        if (buttonContainer) {
            buttonContainer.appendChild(button);
        }
    }, []);

    const getGooglePaymentDataRequest = React.useCallback((configuration) => {
        const paymentDataRequest = Object.assign({ emailRequired: true }, baseRequest);

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
    }, []);

    const prefetchGooglePaymentData = React.useCallback((configuration) => {
        const paymentDataRequest = getGooglePaymentDataRequest(configuration);

        paymentDataRequest.transactionInfo = {
            countryCode: configuration.transactionInfo.countryCode,
            currencyCode: configuration.transactionInfo.currencyCode,
            totalPriceStatus: "FINAL",
            totalPrice: configuration.transactionInfo.totalPrice,
        };

        paymentsClient.current.prefetchPaymentData(paymentDataRequest);
    }, []);

    const loadGooglePayButton = React.useCallback(() => {
        const buttonContainer = document.getElementById("googlepay-button-container");

        if (buttonContainer) {
            buttonContainer.innerHTML = "";
        }

        $.ajax({
            type: "POST",
            data: {
                action: "google_pay_configuration",
            },
            url: `${getAdminUrl()}admin-ajax.php`,
            beforeSend: function () {
                $.blockUI({ message: "" });
            },
            success: function (configuration) {
                $.unblockUI();

                if (configuration.config.test_mode) {
                    paymentsClient.current = new google.payments.api.PaymentsClient({
                        environment: "TEST",
                    });
                } else {
                    paymentsClient.current = new google.payments.api.PaymentsClient({
                        environment: "PRODUCTION",
                    });
                }

                const isReadyToPayRequest = Object.assign({}, baseRequest, {
                    allowedPaymentMethods: [getBaseCardPaymentMethod(configuration)],
                });

                paymentsClient.current
                    .isReadyToPay(isReadyToPayRequest)
                    .then(function (response) {
                        $.unblockUI();

                        if (response.result) {
                            addGooglePayButton(configuration);
                            // @todo prefetch payment data to improve performance after confirming site functionality
                            prefetchGooglePaymentData(configuration);
                        }
                    })
                    .catch(function (err) {
                        $.unblockUI();

                        console.error(err);
                    });
            },
            complete: function () {
                $.unblockUI();
            },
        });
    }, []);

    React.useEffect(() => {
        loadGooglePayButton();
    }, [loadGooglePayButton]);

    const waitGooglePayResult = React.useCallback(async () => {
        return new Promise((resolve, reject) => {
            const xpayGooglePayUpdateListener = (event) => {
                if (event.detail.status === "OK") {
                    resolve(event.detail);
                } else {
                    reject(event.detail);
                }

                window.removeEventListener("xpay_google_pay_update", xpayGooglePayUpdateListener);
            };

            window.addEventListener("xpay_google_pay_update", xpayGooglePayUpdateListener);
        });
    }, []);

    React.useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            try {
                const result = await waitGooglePayResult();

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            google_pay_json: JSON.stringify(result.paymentData),
                        },
                    },
                };
            } catch (error) {
                if ("user_abort_ui" in error && error.user_abort_ui) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                    };
                } else {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: __("There was an error", "woocommerce-gateway-nexi-xpay"),
                    };
                }
            }
        });

        return () => {
            unsubscribe();
        };
    }, [
        emitResponse.responseTypes.SUCCESS,
        emitResponse.responseTypes.ERROR,
        onPaymentSetup,
        waitGooglePayResult,
    ]);

    return (
        <div>
            {PaymentMethodIcons && contentIcons.length > 0 && (
                <PaymentMethodIcons contentIcons={contentIcons} align="right" />
            )}

            <div id="googlepay-button-container"></div>
        </div>
    );
};

const getPaymentMethodOptions = () => {
    const label = getLabel();
    const cardIcons = getCreditCardIcons();
    const contentIcons = getContentIcons();

    const options = {
        name: "xpay_googlepay_button",
        content: <CreditCardContent />,
        label: <CreditCardLabel label={label} icons={cardIcons} />,
        edit: <CreditCardContent content={getContent()} icons={contentIcons} />,
        icons: cardIcons,
        canMakePayment: canMakePayment,
        ariaLabel: __(label, "woocommerce-gateway-nexi-xpay"),
        supports: {
            showSavedCards: false,
            showSaveOption: false,
            features: getFeatures(),
        },
    };

    return options;
};

registerPaymentMethod(getPaymentMethodOptions());
