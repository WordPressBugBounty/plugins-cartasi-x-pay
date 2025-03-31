import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getPaymentMethodOptions } from './commons';

const PAYMENT_METHOD_NAME = 'xpay_npg_przelewy24';

registerPaymentMethod( getPaymentMethodOptions( PAYMENT_METHOD_NAME ) );
