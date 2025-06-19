import "./commons.scss";

import { useEffect, useState } from "react";

import $ from "jquery";

import { __ } from "@wordpress/i18n";

const getPaymentMethodConfiguration = (paymentMethodName, configName) => {
    const serverData = wc?.wcSettings?.getSetting(paymentMethodName + "_data", null);

    if (!serverData) {
        throw new Error(paymentMethodName + " initialization data is not available");
    }

    return serverData;
};

const canMakePayment = (paymentMethodName, canMakePaymentParams) => {
    if (getPaymentMethodConfiguration(paymentMethodName)?.can_make_payment ?? false) {
        const minAmount = getMinAmount(paymentMethodName);

        if (minAmount !== null) {
            if (parseInt(canMakePaymentParams.cart.cartTotals.total_price) < minAmount) {
                return false;
            }
        }

        const maxAmount = getMaxAmount(paymentMethodName);

        if (maxAmount !== null) {
            if (parseInt(canMakePaymentParams.cart.cartTotals.total_price) > maxAmount) {
                return false;
            }
        }

        return true;
    }

    return false;
};

const getCreditCardIcons = (paymentMethodName) => {
    return Object.entries(getPaymentMethodConfiguration(paymentMethodName)?.icons ?? []).map(
        ([id, { src, alt }]) => {
            return {
                id,
                src,
                alt,
            };
        },
    );
};

const getContentIcons = (paymentMethodName) => {
    return Object.entries(
        getPaymentMethodConfiguration(paymentMethodName)?.content_icons ?? [],
    ).map(([id, { src, alt }]) => {
        return {
            id,
            src,
            alt,
        };
    });
};

const getContent = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.content ?? "";
};

const getLabel = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.label ?? "";
};

const getMinAmount = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.min_amount ?? null;
};

const getMaxAmount = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.max_amount ?? null;
};

const getFeatures = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.features ?? [];
};

const getShowSavedCards = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.show_saved_cards ?? false;
};

const getShowSaveOption = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.show_save_option ?? false;
};

const getInstallmentsEnabled = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.installments?.enabled ?? false;
};

const getInstallmentsOptions = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.installments?.options ?? [];
};

const getInstallmentsDefaultOption = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.installments?.default_option ?? "";
};

const getInstallmentsTitleText = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.installments?.title_text ?? "";
};

const getInstallmentsOneSolutionText = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.installments?.one_solution_text ?? "";
};

const getInstallmentsIsPagoDil = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.installments?.is_pago_dil ?? false;
};

const getInstallmentsPagoDilAdminUrl = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.installments?.pago_dil_admin_url ?? "";
};

const getInstallmentsPagoDilInstallmentsAmountLabel = (paymentMethodName) => {
    return (
        getPaymentMethodConfiguration(paymentMethodName)?.installments
            ?.pago_dil_installment_amount_label ?? ""
    );
};

const getIsRecurring = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.recurring?.enabled ?? false;
};

const getRecurringDisclaimertext = (paymentMethodName) => {
    return getPaymentMethodConfiguration(paymentMethodName)?.recurring?.disclaimer_text ?? "";
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

const CreditCardContent = ({ paymentMethodName, eventRegistration, components }) => {
    const { PaymentMethodIcons } = components;
    const { onPaymentSetup } = eventRegistration;

    const contentIcons = getContentIcons(paymentMethodName);

    const installmentsEnabled = getInstallmentsEnabled(paymentMethodName);
    const installmentsOptions = getInstallmentsOptions(paymentMethodName);
    const isPagoDil = getInstallmentsIsPagoDil(paymentMethodName);
    const pagoDilAdminUrl = getInstallmentsPagoDilAdminUrl(paymentMethodName);
    const pagoDilInstallmentAmountLabel =
        getInstallmentsPagoDilInstallmentsAmountLabel(paymentMethodName);

    const isRecurring = getIsRecurring(paymentMethodName);

    const [numberOfInstallments, setNumberOfInstallments] = useState(
        getInstallmentsDefaultOption(paymentMethodName),
    );

    const [installmentAmountLabel, setInstallmentAmountLabel] = useState(
        pagoDilInstallmentAmountLabel,
    );

    useEffect(
        () =>
            onPaymentSetup(() => {
                async function handlePaymentProcessing() {
                    const additionalData = {
                        type: "success",
                        meta: {
                            paymentMethodData: {},
                        },
                    };
                    if (installmentsEnabled && installmentsOptions.length > 0) {
                        additionalData.meta.paymentMethodData.nexi_xpay_number_of_installments =
                            numberOfInstallments;
                    }
                    return additionalData;
                }
                return handlePaymentProcessing();
            }),
        [onPaymentSetup, installmentsEnabled, installmentsOptions, numberOfInstallments],
    );

    useEffect(() => {
        if (!isPagoDil || !installmentsEnabled || !installmentsOptions.length > 0) {
            setInstallmentAmountLabel("");
        } else {
            $.ajax({
                type: "POST",
                data: {
                    action: "calc_installments",
                    installments: parseInt(numberOfInstallments),
                },
                url: pagoDilAdminUrl + "admin-ajax.php",
                success: function (response) {
                    setInstallmentAmountLabel(response.installmentsLabel);
                },
                complete: function () {},
            });
        }
    }, [pagoDilAdminUrl, numberOfInstallments, setInstallmentAmountLabel, $]);

    return (
        <>
            <span>{getContent(paymentMethodName)}</span>
            {PaymentMethodIcons && contentIcons.length > 0 && (
                <PaymentMethodIcons contentIcons={contentIcons} align="right" />
            )}
            {installmentsEnabled && installmentsOptions.length > 0 && (
                <div className="wc-gateway-nexi-xpay-block-checkout-additional-info">
                    <div>
                        <label className="wc-gateway-nexi-xpay-block-checkout-row">
                            {getInstallmentsTitleText(paymentMethodName)}
                        </label>
                    </div>
                    <select
                        defaultValue=""
                        onChange={(e) => setNumberOfInstallments(e.target.value)}
                    >
                        {!isPagoDil && (
                            <option value="">
                                {getInstallmentsOneSolutionText(paymentMethodName)}
                            </option>
                        )}
                        {installmentsOptions.map((opt) => {
                            return <option value={opt}>{opt}</option>;
                        })}
                    </select>
                    {isPagoDil && (
                        <div className="wc-gateway-nexi-xpay-block-checkout-row">
                            <span>{installmentAmountLabel}</span>
                        </div>
                    )}
                </div>
            )}
            {isRecurring && (
                <div>
                    <span>{getRecurringDisclaimertext(paymentMethodName)}</span>
                </div>
            )}
        </>
    );
};

export const getPaymentMethodOptions = (
    paymentMethodName,
    additionalCanMakePaymentCallable = undefined,
) => {
    let _canMakePayment = (canMakePaymentParams) =>
        canMakePayment(paymentMethodName, canMakePaymentParams);

    if (additionalCanMakePaymentCallable !== undefined) {
        _canMakePayment = (canMakePaymentParams) => {
            return (
                canMakePayment(paymentMethodName, canMakePaymentParams) &&
                additionalCanMakePaymentCallable(canMakePaymentParams)
            );
        };
    }

    const label = getLabel(paymentMethodName);
    const cardIcons = getCreditCardIcons(paymentMethodName);
    const contentIcons = getContentIcons(paymentMethodName);

    const options = {
        name: paymentMethodName,
        content: <CreditCardContent paymentMethodName={paymentMethodName} />,
        label: <CreditCardLabel label={label} icons={cardIcons} />,
        edit: <CreditCardContent content={getContent(paymentMethodName)} icons={contentIcons} />,
        icons: cardIcons,
        canMakePayment: _canMakePayment,
        ariaLabel: __(label, "woocommerce-gateway-nexi-xpay"),
        supports: {
            showSavedCards: getShowSavedCards(paymentMethodName),
            showSaveOption: getShowSaveOption(paymentMethodName),
            features: getFeatures(paymentMethodName),
        },
    };

    return options;
};
