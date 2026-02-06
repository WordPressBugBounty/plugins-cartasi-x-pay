export const createXPayBuildForm = (
    paymentPayload,
    buildStyle,
    enabled3ds,
    identifier,
    tokenData = null,
) => {
    try {
        if (paymentPayload === undefined || paymentPayload === null) {
            return null;
        }

        // Configurazione del pagamento
        const xpayConfig = {
            baseConfig: {
                apiKey: paymentPayload.apiKey,
                enviroment: paymentPayload.enviroment,
            },
            paymentParams: {
                amount: paymentPayload.amount,
                currency: paymentPayload.divisa,
                url: "",
                urlPost: "",
                urlBack: "",
            },
            customParams: {},
            language: paymentPayload.language,
        };

        if (enabled3ds === 1) {
            const tds_param = {
                buyer: {},
                destinationAddress: {},
                billingAddress: {},
                cardHolderAcctInfo: {},
            };

            if (paymentPayload.Buyer_email !== "") {
                tds_param.buyer.email = paymentPayload.Buyer_email;
            }
            if (paymentPayload.Buyer_homePhone !== "") {
                tds_param.buyer.homePhone = paymentPayload.Buyer_homePhone;
            }
            if (paymentPayload.Buyer_account !== "") {
                tds_param.buyer.account = paymentPayload.Buyer_account;
            }

            if (paymentPayload.Dest_city !== "") {
                tds_param.destinationAddress.city = paymentPayload.Dest_city;
            }
            if (paymentPayload.Dest_country !== "") {
                tds_param.destinationAddress.countryCode = paymentPayload.Dest_country;
            }
            if (paymentPayload.Dest_street !== "") {
                tds_param.destinationAddress.street = paymentPayload.Dest_street;
            }
            if (paymentPayload.Dest_street2 !== "") {
                tds_param.destinationAddress.street2 = paymentPayload.Dest_street2;
            }
            if (paymentPayload.Dest_cap !== "") {
                tds_param.destinationAddress.postalCode = paymentPayload.Dest_cap;
            }
            if (paymentPayload.Dest_state !== "") {
                tds_param.destinationAddress.stateCode = paymentPayload.Dest_state;
            }

            if (paymentPayload.Bill_city !== "") {
                tds_param.billingAddress.city = paymentPayload.Bill_city;
            }
            if (paymentPayload.Bill_country !== "") {
                tds_param.billingAddress.countryCode = paymentPayload.Bill_country;
            }
            if (paymentPayload.Bill_street !== "") {
                tds_param.billingAddress.street = paymentPayload.Bill_street;
            }
            if (paymentPayload.Bill_street2 !== "") {
                tds_param.billingAddress.street2 = paymentPayload.Bill_street2;
            }
            if (paymentPayload.Bill_cap !== "") {
                tds_param.billingAddress.postalCode = paymentPayload.Bill_cap;
            }
            if (paymentPayload.Bill_state !== "") {
                tds_param.billingAddress.stateCode = paymentPayload.Bill_state;
            }

            if (paymentPayload.chAccDate !== "") {
                tds_param.cardHolderAcctInfo.chAccDate = paymentPayload.chAccDate;
            }
            if (paymentPayload.chAccAgeIndicator !== "") {
                tds_param.cardHolderAcctInfo.chAccAgeIndicator = paymentPayload.chAccAgeIndicator;
            }
            if (paymentPayload.nbPurchaseAccount !== "") {
                tds_param.cardHolderAcctInfo.nbPurchaseAccount = paymentPayload.nbPurchaseAccount;
            }
            if (paymentPayload.destinationAddressUsageDate !== "") {
                tds_param.cardHolderAcctInfo.destinationAddressUsageDate =
                    paymentPayload.destinationAddressUsageDate;
            }
            if (paymentPayload.destinationNameIndicator !== "") {
                tds_param.cardHolderAcctInfo.destinationNameIndicator =
                    paymentPayload.destinationNameIndicator;
            }

            if (Object.keys(tds_param.buyer).length === 0) {
                delete tds_param.buyer;
            }
            if (Object.keys(tds_param.destinationAddress).length === 0) {
                delete tds_param.destinationAddress;
            }
            if (Object.keys(tds_param.billingAddress).length === 0) {
                delete tds_param.billingAddress;
            }
            if (Object.keys(tds_param.cardHolderAcctInfo).length === 0) {
                delete tds_param.cardHolderAcctInfo;
            }

            xpayConfig.informazioniSicurezza = tds_param;
        }

        var operationType;

        if (tokenData !== null) {
            xpayConfig.paymentParams.transactionId = tokenData.cod_trans_cvv;
            xpayConfig.paymentParams.timeStamp = tokenData.timestamp_cvv;
            xpayConfig.paymentParams.mac = tokenData.mac_cvv;

            xpayConfig.customParams.num_contratto = "" + tokenData.name;

            xpayConfig.serviceType = "paga_oc3d";
            xpayConfig.requestType = "PR";

            operationType = XPay.OPERATION_TYPES.CARD;
        } else {
            xpayConfig.paymentParams.transactionId = paymentPayload.transactionId;
            xpayConfig.paymentParams.timeStamp = paymentPayload.timestamp;
            xpayConfig.paymentParams.mac = paymentPayload.mac;

            operationType = XPay.OPERATION_TYPES.SPLIT_CARD;
        }

        // Configurazione SDK
        XPay.setConfig(xpayConfig);

        // Creazione dell elemento carta
        const card = XPay.create(operationType, buildStyle);

        if (tokenData !== null) {
            document.getElementById(identifier).innerHTML = "";

            card.mount(identifier);
        } else {
            document.getElementById("xpay-pan").innerHTML = "";
            document.getElementById("xpay-expiry").innerHTML = "";
            document.getElementById("xpay-cvv").innerHTML = "";

            card.mount("xpay-pan", "xpay-expiry", "xpay-cvv");
        }

        return card;
    } catch (error) {
        console.error(error);
    }

    return null;
};
