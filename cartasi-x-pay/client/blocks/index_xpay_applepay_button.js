import React from "react";

import $ from "jquery";

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";

const getPaymentMethodConfiguration = () => {
    const serverData = wc?.wcSettings?.getSetting("xpay_applepay_button_data", null);

    if (!serverData) {
        throw new Error("xpay_applepay_button initialization data is not available");
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

const CreditCardContent = ({ eventRegistration, emitResponse, onSubmit, components }) => {
    const { PaymentMethodIcons } = components;

    const contentIcons = getContentIcons();

    const { onPaymentSetup, onShippingRateSelectSuccess } = eventRegistration;

    const [configuration, setConfiguration] = React.useState(null);

    React.useEffect(() => {
        $(".wc-block-components-checkout-place-order-button").hide();

        return () => {
            $(".wc-block-components-checkout-place-order-button").show();
        };
    }, []);

    const buttonRef = React.useRef(null);
    const paymentData = React.useRef(null);

    const { validationStore } = window.wc.wcBlocksData;

    const store = useSelect((select) => {
        return select(validationStore);
    });

    const onApplePayButtonClicked = React.useCallback(() => {
        const hasValidationErrors = store.hasValidationErrors();

        if (!hasValidationErrors) {
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
                    url: `${getAdminUrl()}admin-ajax.php`,
                    success: function (merchantSession) {
                        session.completeMerchantValidation(merchantSession);
                    },
                });
            };

            session.onpaymentauthorized = function (event) {
                paymentData.current = event.payment;

                onSubmit();

                session.completePayment({ status: 0 });
            };

            session.begin();
        }
    }, [onSubmit, configuration, store]);

    React.useEffect(() => {
        if (buttonRef.current !== null) {
            buttonRef.current.addEventListener("click", onApplePayButtonClicked);
        }

        return () => {
            if (buttonRef.current !== null) {
                buttonRef.current.removeEventListener("click", onApplePayButtonClicked);
            }
        };
    }, [onApplePayButtonClicked]);

    const createApplePayButton = React.useCallback(
        (buttonStyle, type, locale, buttonRef) =>
            React.createElement("apple-pay-button", {
                buttonstyle: buttonStyle,
                type,
                locale,
                ref: buttonRef,
            }),
        [],
    );

    const loadApplePayConfiguration = React.useCallback(() => {
        $.ajax({
            type: "POST",
            data: {
                action: "apple_pay_configuration",
            },
            url: `${getAdminUrl()}admin-ajax.php`,
            beforeSend: function () {
                $.blockUI({ message: "" });
            },
            success: function (response) {
                $.unblockUI();

                setConfiguration(response);
            },
            complete: function () {
                $.unblockUI();
            },
        });
    }, []);

    React.useEffect(() => {
        loadApplePayConfiguration();
    }, [loadApplePayConfiguration]);

    React.useEffect(() => {
        const unsubscribe = onShippingRateSelectSuccess(() => {
            loadApplePayConfiguration();

            return true;
        });
        return unsubscribe;
    }, [onShippingRateSelectSuccess]);

    React.useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        apple_pay_json: JSON.stringify(paymentData.current),
                    },
                },
            };
        });

        return () => {
            unsubscribe();
        };
    }, [emitResponse.responseTypes.SUCCESS, emitResponse.responseTypes.ERROR, onPaymentSetup]);

    return (
        <div class="class-a">
            {PaymentMethodIcons && contentIcons.length > 0 && (
                <PaymentMethodIcons contentIcons={contentIcons} align="right" />
            )}

            <div class="class-b">
                {configuration !== null &&
                    createApplePayButton(
                        configuration.button_style,
                        configuration.button_type,
                        configuration.button_locale,
                        buttonRef,
                    )}
            </div>
        </div>
    );
};

const getPaymentMethodOptions = () => {
    const label = getLabel();
    const cardIcons = getCreditCardIcons();
    const contentIcons = getContentIcons();

    const options = {
        name: "xpay_applepay_button",
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
