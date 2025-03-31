import './commons.scss';
import './xpay-build.scss';

import React, {
  useEffect,
  useState,
} from 'react';

import $ from 'jquery';
import _ from 'lodash';

import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';

import {
  AddCheckoutErrorEventListener,
  AddDocumentReadyEventListener,
  AddXpayCardErrorEventListener,
  AddXpayNonceEventListener,
  CreateXpayBuildForm,
  XPAY_NONCE_SELECTOR,
} from './commons-xpay-build';

const PAYMENT_METHOD_NAME = 'xpay_build'

const getPaymentMethodConfiguration = ( paymentMethodName, configName ) => {

    const serverData = wc?.wcSettings?.getSetting(
        paymentMethodName + '_data',
        null
    );

    if ( !serverData ) {
        throw new Error( paymentMethodName + ' initialization data is not available' );
    }

    return serverData;
};

const canMakePayment = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'can_make_payment' )?.can_make_payment ?? false;
};

const getCreditCardIcons = ( paymentMethodName ) => {
    return Object.entries( getPaymentMethodConfiguration( paymentMethodName, 'credit_cards_icons' )?.icons ?? [] ).map(
        ( [id, { src, alt }] ) => {
            return {
                id,
                src,
                alt,
            };
        }
    );
};

const getContentIcons = ( paymentMethodName ) => {
    return Object.entries( getPaymentMethodConfiguration( paymentMethodName, 'content_icons' )?.content_icons ?? [] ).map(
        ( [id, { src, alt }] ) => {
            return {
                id,
                src,
                alt,
            };
        }
    );
};

const getContent = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'content' )?.content ?? '';
};

const getLabel = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'label' )?.label ?? '';
};

const getFeatures = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'features' )?.features ?? [];
};

const getShowSavedCards = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'saved_cards' )?.show_saved_cards ?? false;
};

const getShowSaveOption = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'save_option' )?.show_save_option ?? false;
};

const getIsRecurring = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'recurring_enabled' )?.recurring?.enabled ?? false;
};

const getRecurringDisclaimertext = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'recurring_disclaimer_text' )?.recurring?.disclaimer_text ?? '';
};

const getAdminUrl = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'admin_url' )?.admin_url ?? '';
};

const getErrorMessage = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'error_message' )?.error_message ?? '';
};

const getBorderColorOk = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'border_color_ok' )?.border_color_ok ?? '';
};

const getBorderColorKo = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'border_color_ko' )?.border_color_ko ?? '';
};

const get3ds20Enabled = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'enable_3ds20' )?.enable_3ds20 ?? '';
};

const getPaymentPayload = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'payment_payload' )?.payment_payload ?? '';
};

const getBuildStyle = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'build_style' )?.build_style ?? '';
};

const CreditCardLabel = ( { label, icons, components } ) => {
    const { PaymentMethodLabel, PaymentMethodIcons } = components;

    return <>
        <PaymentMethodLabel text={label} />
        {
            PaymentMethodIcons && icons.length > 0 && (
                <PaymentMethodIcons icons={icons} align="right" />
            )
        }
    </>
};

const CreditCardContent = ( { paymentMethodName, components, eventRegistration, shouldSavePayment } ) => {

    const PAYMENT_METHOD_INPUT_ID = '#radio-control-wc-payment-method-options-xpay_build';

    const { PaymentMethodIcons } = components;

    const { onPaymentSetup } = eventRegistration;

    const buildStyle = getBuildStyle( paymentMethodName );
    const contentIcons = getContentIcons( paymentMethodName );
    const isRecurring = getIsRecurring( paymentMethodName );

    const [paymentPayload, setPaymentPayload] = useState( getPaymentPayload( paymentMethodName ) )
    const [xpayCard, setXpayCard] = useState( {} )
    const [xpayErrorMessage, setXpayErrorMessage] = useState( "" )

    const [xpayCardBorderColorStyle, setXpayCardBorderColorStyle] = useState( getBorderColorOk( paymentMethodName ) )

    const [internalShouldSavePayment, setInternalShouldSavePayment] = useState( false );

    const [cardDetail, setCardDetail] = useState( null )
    const [xpayIdOperazione, setXpayIdOperazione] = useState( "" )
    const [xpayTimestamp, setXpayTimestamp] = useState( "" )
    const [xpayEsito, setXpayEsito] = useState( "" )
    const [xpayMac, setXpayMac] = useState( "" )

    useEffect(
        () => {
            if ( shouldSavePayment === internalShouldSavePayment ) {
                return;
            }

            setInternalShouldSavePayment( shouldSavePayment )

            let requestType = "PA";

            if ( shouldSavePayment ) {
                requestType = "PP";
            }

            XPay.updateConfig( xpayCard["xpay-card"], {
                serviceType: "paga_oc3d",
                requestType: requestType
            } );
            setXpayCard( xpayCard )
        },
        [
            shouldSavePayment,
            internalShouldSavePayment
        ]

    );

    const renderXpayBuild = React.useCallback( () => {
        if ( $( PAYMENT_METHOD_INPUT_ID ).is( ":checked" ) ) {
            XPay.init();

            setXpayCard( ( prevXpayCard ) => {
                CreateXpayBuildForm( prevXpayCard );
                return prevXpayCard;
            } );
        }
    }, [PAYMENT_METHOD_INPUT_ID] );

    const xpayNonce = React.useCallback( () => {
        if ( $( PAYMENT_METHOD_INPUT_ID ).is( ":checked" ) ) {
            if ( $( XPAY_NONCE_SELECTOR ).val().length === 0 ) {
                $( "#xpay_build_num_contratto" ).val( $( "#xpay_build_num_contratto" ).attr( "data-new-card-value" ) );

                setPaymentPayload( ( prevPaymentPayload ) => {
                    prevPaymentPayload.transactionId = $( "#xpay_build_transactionId" ).attr( "data-new-card-value" );
                    return prevPaymentPayload;
                } );

                setXpayCard( ( prevXpayCard ) => {
                    XPay.createNonce( "wc-" + paymentMethodName + "-cc-form", prevXpayCard["xpay-card"] );
                    return prevXpayCard;
                } );

                return false;
            } else {
                return true;
            }
        }
    }, [PAYMENT_METHOD_INPUT_ID, XPAY_NONCE_SELECTOR] );

    useEffect(
        () => {
            AddXpayNonceEventListener(
                PAYMENT_METHOD_INPUT_ID,
                setXpayIdOperazione,
                setXpayTimestamp,
                setXpayEsito,
                setXpayMac,
                setCardDetail,
                setXpayErrorMessage,
            )
            AddXpayCardErrorEventListener(
                getBorderColorOk( paymentMethodName ),
                getBorderColorKo( paymentMethodName ),
                setXpayErrorMessage,
                setXpayCardBorderColorStyle,
            )
            AddCheckoutErrorEventListener( PAYMENT_METHOD_INPUT_ID )
            AddDocumentReadyEventListener( xpayNonce )
            renderXpayBuild()
        },
        [renderXpayBuild, xpayNonce]
    );

    useEffect(
        () =>
            onPaymentSetup( () => {
                async function handlePaymentProcessing() {
                    const additionalData = {
                        type: 'success',
                        meta: {
                            paymentMethodData: {
                                xpay_nonce: $( XPAY_NONCE_SELECTOR ).val(),
                                transaction_id: paymentPayload.transactionId,
                                divisa: paymentPayload.divisa,
                                brand_carta: cardDetail?.brand ?? '',
                                pan_carta: cardDetail?.pan ?? '',
                                scadenza_carta: cardDetail?.scadenza ?? '',
                            },
                        },
                    }
                    return additionalData;
                }
                return handlePaymentProcessing();
            } ),
        [
            onPaymentSetup,
            paymentPayload,
            cardDetail,
        ]
    );

    return <>
        <span >{getContent( paymentMethodName )}</span>
        {
            PaymentMethodIcons && contentIcons.length > 0 && (
                <PaymentMethodIcons contentIcons={contentIcons} align="right" />
            )
        }
        <fieldset id={"wc-" + paymentMethodName + "-cc-form"} class="wc-credit-card-form wc-payment-form">
            <input type="hidden" id="xpay_msg_err" value={getErrorMessage( paymentMethodName )} />
            <input type="hidden" id="xpay_new_payment_info" value={JSON.stringify( paymentPayload )} />
            <input type="hidden" name="divisa" value={paymentPayload.divisa} />
            <input type="hidden" name="transactionId" id="xpay_build_transactionId" data-new-card-value={paymentPayload.transactionId} />
            <input type="hidden" id="xpay_build_style" value={buildStyle} />
            <input type="hidden" id="xpay_build_3ds" value={get3ds20Enabled( paymentMethodName )} />

            {/* <!-- Contiene il form dei dati carta --> */}
            <div id="xpay-card" style={{ border: '1px solid ' + xpayCardBorderColorStyle, padding: '3px;', 'max-width': '420px;' }}>
            </div>

            {/* <!-- Contiene gli errori --> */}
            <div id="xpay-card-errors">
                {xpayErrorMessage}
            </div>
            <br />

            {/* <!-- input valorizzati dopo la chiamata "creaNonce" --> */}
            <input type="hidden" name="xpayNonce" id="xpayNonce" />
            <input type="hidden" name="xpayIdOperazione" id="xpayIdOperazione" value={xpayIdOperazione} />
            <input type="hidden" name="xpayTimeStamp" id="xpayTimeStamp" value={xpayTimestamp} />
            <input type="hidden" name="xpayEsito" id="xpayEsito" value={xpayEsito} />
            <input type="hidden" name="xpayMac" id="xpayMac" value={xpayMac} />

            {
                isRecurring &&
                getRecurringDisclaimertext( paymentMethodName )
            }
            <div class="clear"></div>
        </fieldset>
    </>
};

const SavedTokenComponent = ( { paymentMethodName, eventRegistration, token } ) => {

    const PAYMENT_TOKEN_INPUT_ID = "#radio-control-wc-payment-method-saved-tokens-" + token;

    const [paymentPayload, setPaymentPayload] = useState( getPaymentPayload( paymentMethodName ) );
    const buildStyle = getBuildStyle( paymentMethodName );
    const isRecurring = getIsRecurring( paymentMethodName );

    const { onPaymentSetup } = eventRegistration;

    const [xpayIdOperazione, setXpayIdOperazione] = useState( "" )
    const [xpayTimestamp, setXpayTimestamp] = useState( "" )
    const [xpayEsito, setXpayEsito] = useState( "" )
    const [xpayMac, setXpayMac] = useState( "" );

    const [xpayErrorMessage, setXpayErrorMessage] = useState( "" )
    const [xpayCardBorderColorStyle, setXpayCardBorderColorStyle] = useState( getBorderColorOk( paymentMethodName ) )

    const [tokenData, setTokenData] = useState( null );

    const [cardDetail, setCardDetail] = useState( null );

    const [xpayCard, setXpayCard] = useState( {} );

    const renderXpayBuildSavedToken = React.useCallback( () => {
        if ( $( PAYMENT_TOKEN_INPUT_ID ).is( ":checked" ) ) {

            XPay.init();

            setXpayCard( ( prevXpayCard ) => {
                CreateXpayBuildForm( prevXpayCard, "xpay-card-cvv-" + token, true );
                return prevXpayCard;
            } );
        }
    }, [PAYMENT_TOKEN_INPUT_ID] );

    const xPayNonceSavedToken = React.useCallback( () => {
        if ( $( PAYMENT_TOKEN_INPUT_ID ).is( ":checked" ) ) {
            if ( $( XPAY_NONCE_SELECTOR ).val().length === 0 ) {

                const tokenObject = $( "div[data-wc-id='" + token + "']" );

                const instanceId = "xpay-card-cvv-" + token;

                setPaymentPayload( ( prevPaymentPayload ) => {
                    prevPaymentPayload.transactionId = tokenObject.attr( "data-codTransCvv" );
                    return prevPaymentPayload;
                } );

                $( "#xpay_build_num_contratto" ).val( "" );

                setXpayCard( ( prevXpayCard ) => {
                    XPay.createNonce( "wc-" + paymentMethodName + "-cc-form", prevXpayCard[instanceId] );
                    return prevXpayCard;
                } )

                return false;
            } else {
                return true;
            }
        }
    }, [PAYMENT_TOKEN_INPUT_ID, XPAY_NONCE_SELECTOR] )

    useEffect(
        () =>
            onPaymentSetup( () => {
                async function handlePaymentProcessing() {
                    const additionalData = {
                        type: 'success',
                        meta: {
                            paymentMethodData: {
                                xpay_nonce: $( XPAY_NONCE_SELECTOR ).val(),
                                transaction_id: paymentPayload.transactionId,
                                divisa: paymentPayload.divisa,
                                brand_carta: cardDetail?.brand ?? '',
                                pan_carta: cardDetail?.pan ?? '',
                                scadenza_carta: cardDetail?.scadenza ?? '',
                                "wc-xpay_build-new-payment-token": false,
                            },
                        },
                    }
                    return additionalData;
                }
                return handlePaymentProcessing();
            } ),
        [
            onPaymentSetup,
            paymentPayload,
            cardDetail,
        ]
    );

    useEffect(
        () => {
            AddXpayNonceEventListener(
                PAYMENT_TOKEN_INPUT_ID,
                setXpayIdOperazione,
                setXpayTimestamp,
                setXpayEsito,
                setXpayMac,
                setCardDetail,
                setXpayErrorMessage,
            )
            AddXpayCardErrorEventListener(
                getBorderColorOk( paymentMethodName ),
                getBorderColorKo( paymentMethodName ),
                setXpayErrorMessage,
                setXpayCardBorderColorStyle,
            )
            AddCheckoutErrorEventListener( PAYMENT_TOKEN_INPUT_ID )
            AddDocumentReadyEventListener( xPayNonceSavedToken )

            $.ajax( {
                type: 'POST',
                data: {
                    action: 'xpay_build_block_checkout_token_data',
                    token_id: token
                },
                url: getAdminUrl( paymentMethodName ) + "admin-ajax.php",
                success: function ( response ) {
                    setTokenData( response );
                },
                complete: function () { }
            } );
        },
        [xPayNonceSavedToken, PAYMENT_TOKEN_INPUT_ID]
    );

    useEffect(
        () => {
            if ( tokenData !== null ) {
                renderXpayBuildSavedToken();
            }
        },
        [tokenData, renderXpayBuildSavedToken]
    );

    return <fieldset id={"wc-" + paymentMethodName + "-cc-form"} class="wc-credit-card-form wc-payment-form">
        <input type="hidden" id="xpay_msg_err" value={getErrorMessage( paymentMethodName )} />
        <input type="hidden" id="xpay_new_payment_info" value={JSON.stringify( paymentPayload )} />
        <input type="hidden" name="divisa" value={paymentPayload.divisa} />
        <input type="hidden" name="transactionId" id="xpay_build_transactionId" data-new-card-value={paymentPayload.transactionId} />
        <input type="hidden" id="xpay_build_style" value={buildStyle} />
        <input type="hidden" id="xpay_build_border_color_default" value={getBorderColorOk( paymentMethodName )} />
        <input type="hidden" id="xpay_build_border_color_error" value={getBorderColorKo( paymentMethodName )} />
        <input type="hidden" id="xpay_build_3ds" value={get3ds20Enabled( paymentMethodName )} />

        <div id="xpay-card" style={{ border: '1px solid ' + xpayCardBorderColorStyle, padding: '3px;', 'max-width': '420px;' }}>
            <div
                class="xpay-card-cvv"
                id={'xpay-card-cvv-' + token}
                data-wc-id={token}
                data-token={tokenData?.name}
                data-codTransCvv={tokenData?.cod_trans_cvv}
                data-timestampCvv={tokenData?.timestamp_cvv}
                data-macCvv={tokenData?.mac_cvv}
                style={tokenData?.style}
            ></div>
        </div>

        {/* <!-- Contiene gli errori --> */}
        <div id="xpay-card-errors">
            {xpayErrorMessage}
        </div>
        <br />

        {/* <!-- input valorizzati dopo la chiamata "creaNonce" --> */}
        <input type="hidden" name="xpayNonce" id="xpayNonce" />
        <input type="hidden" name="xpayIdOperazione" id="xpayIdOperazione" value={xpayIdOperazione} />
        <input type="hidden" name="xpayTimeStamp" id="xpayTimeStamp" value={xpayTimestamp} />
        <input type="hidden" name="xpayEsito" id="xpayEsito" value={xpayEsito} />
        <input type="hidden" name="xpayMac" id="xpayMac" value={xpayMac} />

        {
            isRecurring &&
            getRecurringDisclaimertext( paymentMethodName )
        }
        <div class="clear"></div>
    </fieldset>
}

const getPaymentMethodOptions = ( paymentMethodName, additionalCanMakePaymentCallable = undefined ) => {

    let _canMakePayment = () => canMakePayment( paymentMethodName );
    if ( additionalCanMakePaymentCallable !== undefined ) {
        _canMakePayment = () => { canMakePayment( paymentMethodName ) && additionalCanMakePaymentCallable() };
    }

    const label = getLabel( paymentMethodName );
    const cardIcons = getCreditCardIcons( paymentMethodName );
    const contentIcons = getContentIcons( paymentMethodName );

    const options = {
        savedTokenComponent: <SavedTokenComponent paymentMethodName={paymentMethodName}></SavedTokenComponent>,
        name: paymentMethodName,
        content:
            <CreditCardContent paymentMethodName={paymentMethodName} />
        ,
        label: <CreditCardLabel label={label} icons={cardIcons} />,
        edit: <CreditCardContent content={getContent( paymentMethodName )} icons={contentIcons} />,
        icons: cardIcons,
        canMakePayment: _canMakePayment,
        ariaLabel: __( label, 'woocommerce-gateway-nexi-xpay' ),
        supports: {
            showSavedCards: getShowSavedCards( paymentMethodName ),
            showSaveOption: getShowSaveOption( paymentMethodName ),
            features: getFeatures( paymentMethodName ),
        },
    };

    return options;
}

registerPaymentMethod( getPaymentMethodOptions( PAYMENT_METHOD_NAME ) );
