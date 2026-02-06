import "./commons.scss";
import "./xpay-build.scss";

import React from "react";

import $ from "jquery";

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";

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

const CreditCardContent = ({ eventRegistration, emitResponse }) => {
    const { onPaymentSetup, onCheckoutFail, onShippingRateSelectSuccess } = eventRegistration;

    const isRecurring = getIsRecurring();

    const allCardIcons = getAllCardIcons();

    const [xpayBuild, setXPayBuild] = React.useState(undefined);

    const createBuildSdk = React.useCallback((cssLink) => {
        setXPayBuild(
            new Build({
                onBuildSuccess: function (evtData) {
                    if (evtData.event === "BUILD_SUCCESS") {
                        document.getElementById(evtData.id).classList.remove("with-errors");
                    }
                },
                onBuildError: function (evtData) {
                    if (evtData.event === "BUILD_ERROR") {
                        document.getElementById(evtData.id).classList.add("with-errors");
                    }
                },
                onConfirmError: function (evtData) {
                    if ("validationStatus" in evtData) {
                        evtData.validationStatus.forEach(function (item) {
                            if (document.getElementById(item.id)) {
                                if (item.valid) {
                                    document
                                        .getElementById(item.id)
                                        .classList.remove("with-errors");
                                } else {
                                    document.getElementById(item.id).classList.add("with-errors");
                                }
                            }
                        });
                    }

                    const event = new CustomEvent("npg_build_update", { detail: { status: "KO" } });

                    window.dispatchEvent(event);
                },
                onBuildFlowStateChange: function (evtData) {
                    if (evtData.event === "BUILD_FLOW_STATE_CHANGE") {
                        if (
                            evtData.state === "READY_FOR_PAYMENT" ||
                            evtData.state === "PAYMENT_COMPLETE"
                        ) {
                            const event = new CustomEvent("npg_build_update", {
                                detail: { status: "OK" },
                            });

                            window.dispatchEvent(event);
                        }
                    }
                },
                cssLink: cssLink,
            }),
        );
    }, []);

    const confirmBuild = React.useCallback(() => {
        return new Promise((resolve, reject) => {
            xpayBuild.confirmData();

            const npgBuildUpdateListener = (event) => {
                if (event.detail.status === "OK") {
                    resolve(event.detail);
                } else {
                    reject(event.detail);
                }

                window.removeEventListener("npg_build_update", npgBuildUpdateListener);
            };

            window.addEventListener("npg_build_update", npgBuildUpdateListener);
        });
    }, [xpayBuild]);

    const loadBuildFields = React.useCallback(() => {
        $.ajax({
            type: "POST",
            data: {
                action: "get_build_fields",
            },
            url: `${getAdminUrl()}admin-ajax.php`,
            beforeSend: function () {
                $.blockUI({ message: "" });
            },
            success: function (response) {
                $.unblockUI();

                if (response.error_msg) {
                    $(".npg-build-error-msg-container").html(`<p>${response.error_msg}</p>`);
                } else {
                    createBuildSdk(response.cssLink);

                    for (const field of response.fields) {
                        const iframe = document.createElement("iframe");

                        iframe.src = field.src;
                        iframe.className = "iframe_input";

                        const containerElement = document.getElementById(field.id);

                        if (containerElement !== null && containerElement !== undefined) {
                            containerElement.innerHTML = "";
                            containerElement.appendChild(iframe);
                        } else {
                            const divFormInputRow = document.createElement("div");

                            divFormInputRow.className = "form--input__row";

                            const divFormWrapRow = document.createElement("div");

                            divFormWrapRow.className = "form--wrap__row";

                            divFormInputRow.appendChild(divFormWrapRow);

                            const divFormInputWrap = document.createElement("div");

                            divFormInputWrap.id = field.id;
                            divFormInputWrap.className = "form--input__wrap col-10";

                            divFormWrapRow.appendChild(divFormInputWrap);

                            divFormInputWrap.appendChild(iframe);

                            const nexiXPayExtraFieldsContainer = document.getElementById(
                                "nexi-xpay-extra-fields-container",
                            );

                            if (nexiXPayExtraFieldsContainer) {
                                nexiXPayExtraFieldsContainer.innerHTML = "";
                                nexiXPayExtraFieldsContainer.appendChild(divFormInputRow);
                            }
                        }
                    }

                    const surnameInputIsPresent = response.fields.find(function (field) {
                        return field.id === "CARDHOLDER_SURNAME";
                    });

                    if (surnameInputIsPresent) {
                        document.getElementById("CARDHOLDER_NAME").classList.remove("col-10");
                        document.getElementById("CARDHOLDER_NAME").classList.add("col-5");

                        document.getElementById("CARDHOLDER_SURNAME").classList.add("col-5");
                    } else {
                        document.getElementById("CARDHOLDER_NAME").classList.remove("col-5");
                        document.getElementById("CARDHOLDER_NAME").classList.add("col-10");

                        document.getElementById("CARDHOLDER_SURNAME").classList.remove("col-10");
                    }
                }
            },
            complete: function () {
                $.unblockUI();
            },
        });
    }, []);

    React.useEffect(() => {
        loadBuildFields();
    }, [loadBuildFields]);

    React.useEffect(() => {
        const unsubscribe = onShippingRateSelectSuccess(() => {
            loadBuildFields();

            return true;
        });
        return unsubscribe;
    }, [onShippingRateSelectSuccess, loadBuildFields]);

    React.useEffect(() => {
        const unsubscribe = onCheckoutFail(() => {
            loadBuildFields();

            return true;
        });

        return unsubscribe;
    }, [onCheckoutFail, loadBuildFields]);

    React.useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            try {
                await confirmBuild();

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {},
                    },
                };
            } catch (error) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __("There was an error", "woocommerce-gateway-nexi-xpay"),
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
        confirmBuild,
    ]);

    return (
        <div>
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

            {isRecurring && (
                <div>
                    <span>{getRecurringDisclaimertext()}</span>
                </div>
            )}
        </div>
    );
};

const getPaymentMethodOptions = () => {
    const label = getLabel();
    const cardIcons = getCreditCardIcons();
    const contentIcons = getContentIcons();

    const options = {
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
