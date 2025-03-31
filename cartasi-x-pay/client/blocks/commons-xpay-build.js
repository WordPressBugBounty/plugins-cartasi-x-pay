import $ from 'jquery';

export const BLOCK_CHECKOUT_PLACE_ORDER_BUTTON_SELECTOR = '.wc-block-components-checkout-place-order-button';
export const XPAY_NONCE_SELECTOR = '#xpayNonce';
export const WC_BLOCK_CHECKOUT_FORM_SELECTOR = 'form.wc-block-checkout__form';

export const CreateXpayBuildForm = (
    card = undefined,
    identifier = "xpay-card",
    isSavedMethod = false,
) => {
    try {
        const xpay_new_payment_info_object = document.getElementById( "xpay_new_payment_info" );

        if ( xpay_new_payment_info_object === undefined || xpay_new_payment_info_object === null || card === undefined ) {
            return;
        }

        const xpay_new_payment_info_json = xpay_new_payment_info_object.value;
        const xpay_new_payment_info = JSON.parse( xpay_new_payment_info_json );

        // Configurazione del pagamento
        const xpayConfig = {
            baseConfig: {
                apiKey: xpay_new_payment_info.apiKey,
                enviroment: xpay_new_payment_info.enviroment
            },
            paymentParams: {
                amount: xpay_new_payment_info.amount,
                currency: xpay_new_payment_info.divisa,
                url: "",
                urlPost: "",
                urlBack: ""
            },
            customParams: {},
            language: xpay_new_payment_info.language
        };

        const tds_param = {
            buyer: {},
            destinationAddress: {},
            billingAddress: {},
            cardHolderAcctInfo: {}
        };

        if ( xpay_new_payment_info.Buyer_email !== '' ) {
            tds_param.buyer.email = xpay_new_payment_info.Buyer_email;
        }
        if ( xpay_new_payment_info.Buyer_homePhone !== '' ) {
            tds_param.buyer.homePhone = xpay_new_payment_info.Buyer_homePhone;
        }
        if ( xpay_new_payment_info.Buyer_account !== '' ) {
            tds_param.buyer.account = xpay_new_payment_info.Buyer_account;
        }

        if ( xpay_new_payment_info.Dest_city !== '' ) {
            tds_param.destinationAddress.city = xpay_new_payment_info.Dest_city;
        }
        if ( xpay_new_payment_info.Dest_country !== '' ) {
            tds_param.destinationAddress.countryCode = xpay_new_payment_info.Dest_country;
        }
        if ( xpay_new_payment_info.Dest_street !== '' ) {
            tds_param.destinationAddress.street = xpay_new_payment_info.Dest_street;
        }
        if ( xpay_new_payment_info.Dest_street2 !== '' ) {
            tds_param.destinationAddress.street2 = xpay_new_payment_info.Dest_street2;
        }
        if ( xpay_new_payment_info.Dest_cap !== '' ) {
            tds_param.destinationAddress.postalCode = xpay_new_payment_info.Dest_cap;
        }
        if ( xpay_new_payment_info.Dest_state !== '' ) {
            tds_param.destinationAddress.stateCode = xpay_new_payment_info.Dest_state;
        }

        if ( xpay_new_payment_info.Bill_city !== '' ) {
            tds_param.billingAddress.city = xpay_new_payment_info.Bill_city;
        }
        if ( xpay_new_payment_info.Bill_country !== '' ) {
            tds_param.billingAddress.countryCode = xpay_new_payment_info.Bill_country;
        }
        if ( xpay_new_payment_info.Bill_street !== '' ) {
            tds_param.billingAddress.street = xpay_new_payment_info.Bill_street;
        }
        if ( xpay_new_payment_info.Bill_street2 !== '' ) {
            tds_param.billingAddress.street2 = xpay_new_payment_info.Bill_street2;
        }
        if ( xpay_new_payment_info.Bill_cap !== '' ) {
            tds_param.billingAddress.postalCode = xpay_new_payment_info.Bill_cap;
        }
        if ( xpay_new_payment_info.Bill_state !== '' ) {
            tds_param.billingAddress.stateCode = xpay_new_payment_info.Bill_state;
        }

        if ( xpay_new_payment_info.chAccDate !== '' ) {
            tds_param.cardHolderAcctInfo.chAccDate = xpay_new_payment_info.chAccDate;
        }
        if ( xpay_new_payment_info.chAccAgeIndicator !== '' ) {
            tds_param.cardHolderAcctInfo.chAccAgeIndicator = xpay_new_payment_info.chAccAgeIndicator;
        }
        if ( xpay_new_payment_info.nbPurchaseAccount !== '' ) {
            tds_param.cardHolderAcctInfo.nbPurchaseAccount = xpay_new_payment_info.nbPurchaseAccount;
        }
        if ( xpay_new_payment_info.destinationAddressUsageDate !== '' ) {
            tds_param.cardHolderAcctInfo.destinationAddressUsageDate = xpay_new_payment_info.destinationAddressUsageDate;
        }
        if ( xpay_new_payment_info.destinationNameIndicator !== '' ) {
            tds_param.cardHolderAcctInfo.destinationNameIndicator = xpay_new_payment_info.destinationNameIndicator;
        }

        if ( Object.keys( tds_param.buyer ).length === 0 ) {
            delete tds_param.buyer;
        }
        if ( Object.keys( tds_param.destinationAddress ).length === 0 ) {
            delete tds_param.destinationAddress;
        }
        if ( Object.keys( tds_param.billingAddress ).length === 0 ) {
            delete tds_param.billingAddress;
        }
        if ( Object.keys( tds_param.cardHolderAcctInfo ).length === 0 ) {
            delete tds_param.cardHolderAcctInfo;
        }

        let enabled3ds = parseInt( document.getElementById( "xpay_build_3ds" ).value );

        if ( enabled3ds === 1 ) {
            xpayConfig.informazioniSicurezza = tds_param;
        }

        if ( isSavedMethod ) {
            const cvvDiv = $( "#" + identifier );
            xpayConfig.paymentParams.transactionId = cvvDiv.attr( "data-codTransCvv" );
            xpayConfig.paymentParams.timeStamp = cvvDiv.attr( "data-timestampCvv" );
            xpayConfig.paymentParams.mac = cvvDiv.attr( "data-macCvv" );
            xpayConfig.customParams.num_contratto = "" + cvvDiv.attr( "data-token" );
            xpayConfig.serviceType = "paga_oc3d";
            xpayConfig.requestType = "PR";
        } else {
            xpayConfig.paymentParams.transactionId = xpay_new_payment_info.transactionId;
            xpayConfig.paymentParams.timeStamp = xpay_new_payment_info.timestamp;
            xpayConfig.paymentParams.mac = xpay_new_payment_info.mac;
        }

        // Configurazione SDK
        XPay.setConfig( xpayConfig );

        // Creazione dell elemento carta
        const style = JSON.parse( $( "#xpay_build_style" ).val() );

        card[identifier] = XPay.create( XPay.OPERATION_TYPES.CARD, style );

        document.getElementById( identifier ).innerHTML = "";

        card[identifier].mount( identifier );
    } catch ( error ) {
        console.error( error );
    }
}

export const AttachCreateNonceOnPlaceOrderButtonClick = (
    createNonceHandler = () => { },
) => {
    if ( $( BLOCK_CHECKOUT_PLACE_ORDER_BUTTON_SELECTOR ).length === 0 ) {
        return;
    }
    $( BLOCK_CHECKOUT_PLACE_ORDER_BUTTON_SELECTOR ).off();
    $( BLOCK_CHECKOUT_PLACE_ORDER_BUTTON_SELECTOR ).on( 'click', function () {
        return createNonceHandler();
    } );
}

export const AddXpayNonceEventListener = (
    paymentSelector,
    setXpayIdOperazione = () => { },
    setXpayTimestamp = () => { },
    setXpayEsito = () => { },
    setXpayMac = () => { },
    setCardDetail = () => { },
    setXpayErrorMessage = () => { },

) => {
    $( window ).off( "XPay_Nonce" )
    $( window ).on( "XPay_Nonce", function ( event ) {
        let response = event.detail;
        if ( response.esito && response.esito === "OK" ) {
            $( paymentSelector ).prop( 'disabled', false );

            $( XPAY_NONCE_SELECTOR ).val( response.xpayNonce );
            setXpayIdOperazione( response.idOperazione )
            setXpayTimestamp( response.timeStamp )
            setXpayEsito( response.esito )
            setXpayMac( response.mac )

            const dettaglioCarta = {}
            for ( const prop in response.dettaglioCarta ) {
                dettaglioCarta[prop] = response.dettaglioCarta[prop];
            }
            setCardDetail( dettaglioCarta );

            // Submit del form contenente il nonce verso il server del merchant
            $( BLOCK_CHECKOUT_PLACE_ORDER_BUTTON_SELECTOR ).trigger( 'click' );
        } else {
            // Visualizzazione errore creazione nonce e ripristino bottone form
            setXpayErrorMessage( "[" + response.errore.codice + "] " + response.errore.messaggio )

            $( paymentSelector ).prop( 'disabled', false );

            const htmlErr = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">\n<ul class="woocommerce-error" role="alert"><li>' + $( "#xpay_msg_err" ).val() + "</li></ul></div>";

            $( WC_BLOCK_CHECKOUT_FORM_SELECTOR ).prepend( htmlErr );
            $( "html,body" ).animate( { scrollTop: 0 }, "slow" );
            $( "body" ).trigger( "update_checkout" );
        }
    } )
}

export const AddXpayCardErrorEventListener = (
    borderColorOk,
    borderColorKo,
    setXpayErrorMessage = () => { },
    setXpayCardBorderColorStyle = () => { },
) => {
    $( window ).off( "XPay_Card_Error" )
    $( window ).on( "XPay_Card_Error", function ( event ) {
        if ( event.detail.errorMessage ) {
            setXpayErrorMessage( event.detail.errorMessage )
            setXpayCardBorderColorStyle( borderColorKo )
        } else {
            setXpayErrorMessage( "" )
            setXpayCardBorderColorStyle( borderColorOk )
        }
    } )
}

export const AddCheckoutErrorEventListener = (
    paymentSelector,
) => {
    $( document.body ).on( "checkout_error", function () {
        if (
            $( paymentSelector ).is( ":checked" )
        ) {
            if ( $( XPAY_NONCE_SELECTOR ).val().length !== 0 ) {
                $( WC_BLOCK_CHECKOUT_FORM_SELECTOR ).trigger( "update" );
                $( XPAY_NONCE_SELECTOR ).val( "" );
            }
        }
    } )
}

export const AddDocumentReadyEventListener = (
    createNonceHandler = () => { },
) => {
    $( function () {
        AttachCreateNonceOnPlaceOrderButtonClick( createNonceHandler );
    } )
}
