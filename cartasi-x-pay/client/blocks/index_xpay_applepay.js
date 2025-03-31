import { registerPaymentMethod } from '@woocommerce/blocks-registry';

import { getPaymentMethodOptions } from './commons';

const PAYMENT_METHOD_NAME = 'xpay_applepay';

registerPaymentMethod( getPaymentMethodOptions( PAYMENT_METHOD_NAME, () => window.ApplePaySession ) );
