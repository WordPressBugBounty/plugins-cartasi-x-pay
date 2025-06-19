import "./commons.scss";
import "./xpay-build.scss";

import { useEffect, useState } from "react";

import $ from "jquery";

import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { __ } from "@wordpress/i18n";

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

const getValidationError = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'validation_error' )?.validation_error ?? '';
};

const getSessionError = ( paymentMethodName ) => {
    return getPaymentMethodConfiguration( paymentMethodName, 'session_error' )?.session_error ?? '';
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

const CreditCardContent = ( { paymentMethodName, eventRegistration, components } ) => {

    const { PaymentMethodIcons } = components;

    const { onPaymentSetup, onShippingRateSelectSuccess } = eventRegistration;

    const contentIcons = getContentIcons( paymentMethodName );

    const isRecurring = getIsRecurring( paymentMethodName );

    const adminUrl = getAdminUrl( paymentMethodName );
    const incorrectOrMissingData = getValidationError( paymentMethodName );
    const buildSessionExpired = getSessionError( paymentMethodName );

    const [npgOrderId, setNpgOrderId] = useState( "" );
    const [refreshTrigger, setRefreshTrigger] = useState( false );
    const [reloadingFields, setReloadingFields] = useState( false );
    const [errorMsg, setErrorMsg] = useState( "" );
    const [showLoaderContainer, setShowLoaderContainer] = useState( false );

    const npgCheckAndloadBuild = function () {
        if ( $( 'input[name="payment_method"]:checked' ).val() == "xpay_build" || $( 'input[name="radio-control-wc-payment-method-options"]:checked' ).val() == "xpay_build" ) {
            npgBuildFields();
        } else {
            npgEnableSubmitButton();
        }
    };
    const npgBuildFields = function () {
        $( "#wc-xpay_build-cc-form" ).hide();
        setShowLoaderContainer( true );

        setErrorMsg( "" );
        npgCleanBuildFields();

        $.ajax( {
            type: "POST",
            data: {
                action: "get_build_fields",
                orderId: `${$( "#npg-orderId" ).val()}`,
            },
            url: `${adminUrl}admin-ajax.php`,
            beforeSend: function () {
                setShowLoaderContainer( true );
                npgDisableSubmitButton();
            },
            success: function ( response ) {
                npgDisableSubmitButton();

                setShowLoaderContainer( false );

                if ( response.error_msg ) {
                    $( ".npg-build-error-msg-container" ).html( `<p>${response.error_msg}</p>` );
                } else {
                    setNpgOrderId( response.orderId )

                    let fields = response.fields;

                    for ( const element of fields ) {
                        let iframe = document.createElement( "iframe" );

                        iframe.src = element.src;
                        iframe.className = "iframe-field";

                        $( `#${element.id}` ).html("");
                        $( `#${element.id}` ).append( iframe );
                    }

                    $( "#wc-xpay_build-cc-form" ).show();
                }
            },
            complete: function () {
                setShowLoaderContainer( false );
            },
        } );
    };
    const npgEnableSubmitButton = function () {
        $( ".wc-block-components-checkout-place-order-button" ).attr( "disabled", false );
    };
    const npgDisableSubmitButton = function () {
        $( ".wc-block-components-checkout-place-order-button" ).attr( "disabled", true );
    };


    const npgCleanBuildFields = function () {
        $( ".build-field-row" ).each( function ( _i, fRow ) {
            $( fRow )
                .children( "div" )
                .children( "iframe" )
                .each( function ( _j, field ) {
                    $( field ).remove();
                } );
        } );
    };

    useEffect(
        () => {
            setRefreshTrigger( true );
            const interval = setInterval( () => {
                if ( $( 'input[name="radio-control-wc-payment-method-options"]:checked' ).val() == "xpay_build" ) {
                    $( ".wc-block-components-checkout-place-order-button" ).attr( "disabled", true );
                } else {
                    $( ".wc-block-components-checkout-place-order-button" ).attr( "disabled", false );
                }
            }, 500 );

            setTimeout( () => {
                clearInterval( interval );
            }, 2000 );
        },
        []
    );

    useEffect(
        () =>
            onPaymentSetup( () => {
                async function handlePaymentProcessing() {
                    const additionalData = {
                        type: 'success',
                        meta: {
                            paymentMethodData: {
                                npg_order_id: npgOrderId,
                            },
                        },
                    }
                    return additionalData;
                }
                return handlePaymentProcessing();
            } ),
        [
            onPaymentSetup,
            npgOrderId
        ]
    );

    useEffect(
        () => {
            $( "form.wc-block-checkout__form" ).on( "change", 'input[name="radio-control-wc-payment-method-options"]', function () {
                if ( $( 'input[name="radio-control-wc-payment-method-options"]:checked' ).val() == "xpay_build" ) {
                    $( ".wc-block-components-checkout-place-order-button" ).attr( "disabled", true );
                } else {
                    $( ".wc-block-components-checkout-place-order-button" ).attr( "disabled", false );
                }
            } );
            window.addEventListener( "message", function ( event ) {
                if ( "event" in event.data && "state" in event.data ) {
                    // Nexi sta notificando che si è pronti per il pagamento
                    if (
                        event.data.event === "BUILD_FLOW_STATE_CHANGE" &&
                        event.data.state === "READY_FOR_PAYMENT"
                    ) {
                        $( ".wc-block-components-checkout-place-order-button" ).attr( "disabled", false );
                    }
                }

                if ( event.data.event === "BUILD_ERROR" ) {
                    if ( event.data.errorCode == "HF0001" ) {
                        setErrorMsg( $( "#validation-error" ).val() );
                    } else if ( event.data.errorCode == "HF0003" ) {
                        setErrorMsg( $( "#session-error" ).val() );
                    } else {
                        console.error( event.data );
                    }
                } else {
                    setErrorMsg( "" );
                }
            } );
        },
        []
    );

    useEffect(
        () => {
            if ( !reloadingFields ) {
                setReloadingFields( true )
                npgCheckAndloadBuild()
            }
        },
        [
            refreshTrigger,
            reloadingFields,
            setReloadingFields,
            setNpgOrderId,
            adminUrl,
            $
        ]
    );

    React.useEffect(() => {
        const unsubscribe = onShippingRateSelectSuccess(() => {
            npgBuildFields();

            return true;
        });
        return unsubscribe;
    }, [onShippingRateSelectSuccess, npgBuildFields]);

    return <div>
        <span >{getContent( paymentMethodName )}</span>
        {
            PaymentMethodIcons && contentIcons.length > 0 && (
                <PaymentMethodIcons contentIcons={contentIcons} align="right" />
            )
        }
        {
            <>
                <input type="hidden" id="xpay_admin_url" value={adminUrl} />
                {
                    showLoaderContainer &&
                    <div class="loader-container">
                        <p class="loading"></p>
                    </div>
                }

                <fieldset id={"wc-" + paymentMethodName + "-cc-form"} class="wc-credit-card-form wc-payment-form">
                    <input type="hidden" id="npg-orderId" name="orderId" value="" />
                    <input type="hidden" id="validation-error" value={incorrectOrMissingData} />
                    <input type="hidden" id="session-error" value={buildSessionExpired} />

                    <div class="build-field-row">
                        <div id="CARD_NUMBER"></div>
                    </div>

                    <div class="build-field-row">
                        <div id="EXPIRATION_DATE"></div>
                        <div id="SECURITY_CODE"></div>
                    </div>

                    <div class="build-field-row">
                        <div id="CARDHOLDER_NAME"></div>
                        <div id="CARDHOLDER_SURNAME"></div>
                    </div>

                    <div class="build-field-row">
                        <div id="CARDHOLDER_EMAIL"></div>
                    </div>
                </fieldset>

                <div class="npg-build-error-msg-container">{errorMsg}</div>
            </>
        }
        {
            isRecurring &&
            <div>
                <span>{getRecurringDisclaimertext( paymentMethodName )}</span>
            </div>
        }
    </div>
};

const getPaymentMethodOptions = ( paymentMethodName, additionalCanMakePaymentCallable = undefined ) => {

    let _canMakePayment = () => canMakePayment( paymentMethodName );
    if ( additionalCanMakePaymentCallable !== undefined ) {
        _canMakePayment = () => { canMakePayment( paymentMethodName ) && additionalCanMakePaymentCallable() };
    }

    const label = getLabel( paymentMethodName );
    const cardIcons = getCreditCardIcons( paymentMethodName );
    const contentIcons = getContentIcons( paymentMethodName );

    const options = {
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
