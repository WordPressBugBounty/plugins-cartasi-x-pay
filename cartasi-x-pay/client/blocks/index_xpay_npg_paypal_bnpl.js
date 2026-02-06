import { registerPaymentMethod } from "@woocommerce/blocks-registry";

import { getPaymentMethodOptions } from "./commons";

const PAYMENT_METHOD_NAME = "xpay_npg_paypal_bnpl";

registerPaymentMethod(getPaymentMethodOptions(PAYMENT_METHOD_NAME));
