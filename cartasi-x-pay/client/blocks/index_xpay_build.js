import "./commons.scss";
import "./xpay-build.scss";

import React, { useEffect, useRef, useState } from "react";

import $ from "jquery";
import _ from "lodash";

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";

import { createXPayBuildForm } from "./commons-xpay-build";

const getPaymentMethodConfiguration = () => {
    const serverData = wc?.wcSettings?.getSetting("xpay_build_data", null);

    if (!serverData) {
        throw new Error("xpay_build initialization data is not available");
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

const getAllCardIcons = () => {
    return Object.entries(getPaymentMethodConfiguration()?.all_card_icons ?? []).map(
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

const getShowSavedCards = () => {
    return getPaymentMethodConfiguration()?.show_saved_cards ?? false;
};

const getShowSaveOption = () => {
    return getPaymentMethodConfiguration()?.show_save_option ?? false;
};

const getIsRecurring = () => {
    return getPaymentMethodConfiguration()?.recurring?.enabled ?? false;
};

const getRecurringDisclaimertext = () => {
    return getPaymentMethodConfiguration()?.recurring?.disclaimer_text ?? "";
};

const getAdminUrl = () => {
    return getPaymentMethodConfiguration()?.admin_url ?? "";
};

const getBorderColorOk = () => {
    return getPaymentMethodConfiguration()?.border_color_ok ?? "";
};

const getBorderColorKo = () => {
    return getPaymentMethodConfiguration()?.border_color_ko ?? "";
};

const get3ds20Enabled = () => {
    return getPaymentMethodConfiguration()?.enable_3ds20 ?? "";
};

const getPaymentPayload = () => {
    return getPaymentMethodConfiguration()?.payment_payload ?? "";
};

const getBuildStyle = () => {
    return getPaymentMethodConfiguration()?.build_style ?? "";
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

const CreditCardContent = ({ eventRegistration, shouldSavePayment, emitResponse }) => {
    const { onPaymentSetup, onCheckoutFail, onShippingRateSelectSuccess } = eventRegistration;

    const isRecurring = getIsRecurring();

    const allCardIcons = getAllCardIcons();

    const [paymentPayload, setPaymentPayload] = React.useState(getPaymentPayload());

    const cardXPayRef = useRef(null);

    const [errorMessageXPay, setErrorMessageXPay] = useState("");

    const [xpayCardBorderColorStyle, setXPayCardBorderColorStyle] = useState(getBorderColorOk());

    const [xpayBuildFormReady, setXPayBuildFormReady] = useState(false);

    useEffect(() => {
        if (xpayBuildFormReady) {
            if (isRecurring) {
                XPay.updateConfig(cardXPayRef.current, {
                    serviceType: "paga_multi",
                    requestType: "PP",
                });
            } else if (shouldSavePayment) {
                XPay.updateConfig(cardXPayRef.current, {
                    serviceType: "paga_oc3d",
                    requestType: "PP",
                });
            } else {
                XPay.updateConfig(cardXPayRef.current, {
                    serviceType: "paga_oc3d",
                    requestType: "PA",
                });
            }
        }
    }, [shouldSavePayment, isRecurring, xpayBuildFormReady]);

    const renderXPayBuild = React.useCallback(() => {
        setXPayBuildFormReady(false);

        $.ajax({
            type: "POST",
            data: {
                action: "build_payment_payload",
            },
            url: `${getAdminUrl()}admin-ajax.php`,
            success: function (response) {
                setPaymentPayload(response);

                XPay.init();

                cardXPayRef.current = createXPayBuildForm(
                    response,
                    getBuildStyle(),
                    get3ds20Enabled(),
                    "xpay-card",
                );
            },
        });
    }, []);

    useEffect(() => {
        renderXPayBuild();
    }, [renderXPayBuild]);

    useEffect(() => {
        const unsubscribe = onShippingRateSelectSuccess(() => {
            renderXPayBuild();

            return true;
        });
        return unsubscribe;
    }, [onShippingRateSelectSuccess, renderXPayBuild]);

    useEffect(() => {
        const unsubscribe = onCheckoutFail(() => {
            renderXPayBuild();

            return true;
        });

        return unsubscribe;
    }, [onCheckoutFail, renderXPayBuild]);

    useEffect(() => {
        const xpayReadyListener = () => {
            setXPayBuildFormReady(true);
        };

        window.addEventListener("XPay_Ready", xpayReadyListener);

        return () => {
            window.removeEventListener("XPay_Ready", xpayReadyListener);
        };
    }, []);

    useEffect(() => {
        const xpayCardErrorListener = (event) => {
            if (event.detail.errorMessage) {
                setErrorMessageXPay(event.detail.errorMessage);
                setXPayCardBorderColorStyle(getBorderColorKo());
            } else {
                setErrorMessageXPay("");
                setXPayCardBorderColorStyle(getBorderColorOk());
            }
        };

        window.addEventListener("XPay_Card_Error", xpayCardErrorListener);

        return () => {
            window.removeEventListener("XPay_Card_Error", xpayCardErrorListener);
        };
    }, []);

    const createXPayNonce = React.useCallback((cardRef) => {
        XPay.createNonce(null, cardRef);

        return new Promise((resolve, reject) => {
            const xpayNonceListener = (event) => {
                if (event.detail.esito === "OK") {
                    resolve(event.detail);
                } else {
                    reject(event.detail);
                }

                window.removeEventListener("XPay_Nonce", xpayNonceListener);
            };

            window.addEventListener("XPay_Nonce", xpayNonceListener);

            const xpayCardErrorOnSubmitListener = (event) => {
                if (event.detail.errorMessage) {
                    setErrorMessageXPay(event.detail.errorMessage);
                    setXPayCardBorderColorStyle(getBorderColorKo());

                    reject(event.detail);
                } else {
                    setErrorMessageXPay("");
                    setXPayCardBorderColorStyle(getBorderColorOk());
                }

                window.removeEventListener("XPay_Card_Error", xpayCardErrorOnSubmitListener);
            };

            window.addEventListener("XPay_Card_Error", xpayCardErrorOnSubmitListener);
        });
    }, []);

    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            try {
                const eventDetail = await createXPayNonce(cardXPayRef.current);

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            xpay_nonce: eventDetail.xpayNonce,
                            transaction_id: paymentPayload.transactionId,
                            divisa: paymentPayload.divisa,
                            brand_carta: eventDetail.dettaglioCarta?.brand ?? "",
                            pan_carta: eventDetail.dettaglioCarta?.pan ?? "",
                            scadenza_carta: eventDetail.dettaglioCarta?.scadenza ?? "",
                        },
                    },
                };
            } catch (error) {
                var codice = parseInt(error?.errore?.codice);

                let errorMessage = __("There was an error", "woocommerce-gateway-nexi-xpay");

                if (codice === 600) {
                    errorMessage = __("Payment canceled", "woocommerce-gateway-nexi-xpay");
                }

                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: errorMessage,
                };
            }
        });

        return () => {
            unsubscribe();
        };
    }, [
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
        onPaymentSetup,
        createXPayNonce,
        paymentPayload,
        cardXPayRef,
    ]);

    return (
        <>
            <span>{getContent()}</span>

            <div class="nexixpay-loghi-container flex">
                <div class="internal-container">
                    {allCardIcons.map((cardIcon) => (
                        <div class="img-container" key={cardIcon.id}>
                            <img src={cardIcon.src} alt={cardIcon.alt} />
                        </div>
                    ))}
                </div>
            </div>

            <div class="wc-credit-card-form wc-payment-form nexi-xpay-build">
                <div style={{ display: "table", marginBottom: "30px" }}>
                    <div
                        id="xpay-pan"
                        class="xpay-card-container"
                        style={{
                            maxWidth: "300px",
                            width: "100%",
                            borderColor: xpayCardBorderColorStyle,
                            marginBottom: "5px",
                        }}
                    ></div>
                    <div style={{ display: "flex", flexDirection: "row" }}>
                        <div
                            id="xpay-expiry"
                            class="xpay-card-container"
                            style={{
                                maxWidth: "147.5px",
                                width: "100%",
                                borderColor: xpayCardBorderColorStyle,
                                borderTopWidth: "1",
                                borderRightWidth: "1",
                                marginRight: "5px",
                            }}
                        ></div>
                        <div
                            id="xpay-cvv"
                            class="xpay-card-container"
                            style={{
                                maxWidth: "147.5px",
                                width: "100%",
                                borderColor: xpayCardBorderColorStyle,
                                borderTopWidth: "1",
                            }}
                        ></div>
                    </div>
                </div>

                <div id="xpay-card-errors">{errorMessageXPay}</div>

                {isRecurring && (
                    <div class="reccurring-info-xpay">{getRecurringDisclaimertext()}</div>
                )}
            </div>
        </>
    );
};

const SavedTokenComponent = ({ eventRegistration, token, emitResponse }) => {
    const [paymentPayload, setPaymentPayload] = React.useState(getPaymentPayload());

    const { onPaymentSetup, onCheckoutFail, onShippingRateSelectSuccess } = eventRegistration;

    const [errorMessageXPay, setErrorMessageXPay] = useState("");

    const [xpayCardBorderColorStyle, setXPayCardBorderColorStyle] = useState(getBorderColorOk());

    const cardXPayRef = useRef(null);

    const renderXPayTokenBuild = React.useCallback(() => {
        $.ajax({
            type: "POST",
            data: {
                action: "build_payment_payload",
                token_id: token,
            },
            url: `${getAdminUrl()}admin-ajax.php`,
            success: function (response) {
                setPaymentPayload(response);

                XPay.init();

                cardXPayRef.current = createXPayBuildForm(
                    response,
                    getBuildStyle(),
                    get3ds20Enabled(),
                    "xpay-card-cvv-" + token,
                    response.token_data,
                );
            },
        });
    }, [token]);

    useEffect(() => {
        renderXPayTokenBuild();
    }, [renderXPayTokenBuild]);

    useEffect(() => {
        const unsubscribe = onShippingRateSelectSuccess(() => {
            renderXPayTokenBuild();

            return true;
        });

        return unsubscribe;
    }, [onShippingRateSelectSuccess, renderXPayTokenBuild]);

    useEffect(() => {
        const unsubscribe = onCheckoutFail(() => {
            renderXPayTokenBuild();

            return true;
        });

        return unsubscribe;
    }, [onCheckoutFail, renderXPayTokenBuild]);

    useEffect(() => {
        const unsubscribe = onShippingRateSelectSuccess(() => {
            renderXPayTokenBuild();

            return true;
        });

        return unsubscribe;
    }, [onShippingRateSelectSuccess, renderXPayTokenBuild]);

    useEffect(() => {
        const xpayCardErrorListener = (event) => {
            if (event.detail.errorMessage) {
                setErrorMessageXPay(event.detail.errorMessage);
                setXPayCardBorderColorStyle(getBorderColorKo());
            } else {
                setErrorMessageXPay("");
                setXPayCardBorderColorStyle(getBorderColorOk());
            }
        };

        window.addEventListener("XPay_Card_Error", xpayCardErrorListener);

        return () => {
            window.removeEventListener("XPay_Card_Error", xpayCardErrorListener);
        };
    }, []);

    const createXPayNonce = React.useCallback((cardRef) => {
        XPay.createNonce(null, cardRef);

        return new Promise((resolve, reject) => {
            const xpayNonceListener = (event) => {
                if (event.detail.esito === "OK") {
                    resolve(event.detail);
                } else {
                    reject(event.detail);
                }

                window.removeEventListener("XPay_Nonce", xpayNonceListener);
            };

            window.addEventListener("XPay_Nonce", xpayNonceListener);

            const xpayCardErrorOnSubmitListener = (event) => {
                if (event.detail.errorMessage) {
                    setErrorMessageXPay(event.detail.errorMessage);
                    setXPayCardBorderColorStyle(getBorderColorKo());

                    reject(event.detail);
                } else {
                    setErrorMessageXPay("");
                    setXPayCardBorderColorStyle(getBorderColorOk());
                }

                window.removeEventListener("XPay_Card_Error", xpayCardErrorOnSubmitListener);
            };

            window.addEventListener("XPay_Card_Error", xpayCardErrorOnSubmitListener);
        });
    }, []);

    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            try {
                const eventDetail = await createXPayNonce(cardXPayRef.current);

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            xpay_nonce: eventDetail.xpayNonce,
                            transaction_id: paymentPayload.token_data.cod_trans_cvv,
                            divisa: paymentPayload.divisa,
                            brand_carta: eventDetail.dettaglioCarta?.brand ?? "",
                            pan_carta: eventDetail.dettaglioCarta?.pan ?? "",
                            scadenza_carta: eventDetail.dettaglioCarta?.scadenza ?? "",
                            "wc-xpay-new-payment-token": false,
                        },
                    },
                };
            } catch (error) {
                var codice = parseInt(error?.errore?.codice);

                let errorMessage = __("There was an error", "woocommerce-gateway-nexi-xpay");

                if (codice === 600) {
                    errorMessage = __("Payment canceled", "woocommerce-gateway-nexi-xpay");
                }

                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: errorMessage,
                };
            }
        });

        return () => {
            unsubscribe();
        };
    }, [
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
        onPaymentSetup,
        createXPayNonce,
        paymentPayload,
        cardXPayRef,
    ]);

    return (
        <div class="wc-credit-card-form wc-payment-form nexi-xpay-build">
            <div
                class="xpay-card-cvv xpay-card-container"
                style={{ borderColor: xpayCardBorderColorStyle }}
                id={"xpay-card-cvv-" + token}
            ></div>

            <div id="xpay-card-errors">{errorMessageXPay}</div>
        </div>
    );
};

const getPaymentMethodOptions = () => {
    const label = getLabel();
    const cardIcons = getCreditCardIcons();
    const contentIcons = getContentIcons();

    const options = {
        savedTokenComponent: <SavedTokenComponent />,
        name: "xpay",
        content: <CreditCardContent />,
        label: <CreditCardLabel label={label} icons={cardIcons} />,
        edit: <CreditCardContent content={getContent()} icons={contentIcons} />,
        icons: cardIcons,
        canMakePayment: canMakePayment,
        ariaLabel: __(label, "woocommerce-gateway-nexi-xpay"),
        supports: {
            showSavedCards: getShowSavedCards(),
            showSaveOption: getShowSaveOption(),
            features: getFeatures(),
        },
    };

    return options;
};

registerPaymentMethod(getPaymentMethodOptions());
