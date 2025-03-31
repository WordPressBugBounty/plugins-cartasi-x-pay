import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getPaymentMethodOptions } from './commons';

const PAYMENT_METHOD_NAME = 'index_xpay_npg_googlepay';

registerPaymentMethod( getPaymentMethodOptions( PAYMENT_METHOD_NAME ) );
