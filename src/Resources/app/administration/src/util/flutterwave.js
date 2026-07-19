/**
 * Fully-qualified class name of the Shopware payment handler. Must stay in sync
 * with src/Checkout/Payment/FlutterwavePaymentHandler.php. Kept in one place
 * so a namespace change is a single-line fix rather than a silent breakage
 * across components.
 */
export const FLUTTERWAVE_HANDLER_IDENTIFIER =
    'Kommandhub\\FlutterwaveSW\\Checkout\\Payment\\FlutterwavePaymentHandler';

/**
 * Custom-field key that reliably marks a transaction as Flutterwave's, used as a
 * fallback when the payment-method association is not loaded.
 */
export const FLUTTERWAVE_REFERENCE_FIELD = 'flutterwave_reference';

/**
 * Whether an error represents an aborted/cancelled HTTP request, which should be
 * swallowed silently rather than surfaced to the user.
 *
 * @param {*} error
 * @returns {boolean}
 */
export function isAbortError(error) {
    return error?.code === 'ECONNABORTED'
        || error?.name === 'AbortError'
        || error?.message === 'Request aborted';
}
