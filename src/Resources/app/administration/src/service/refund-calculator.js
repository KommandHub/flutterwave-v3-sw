/**
 * Pure refund math for the Flutterwave admin UI.
 *
 * No Shopware dependencies, so it can be unit-tested in isolation (see
 * refund-calculator.spec.js). The currency-decimal table mirrors the backend
 * FlutterwaveCurrencyHelper — keep the two in sync.
 *
 * The refundable base is the charged amount minus refunds already made (this
 * plugin has a single capture per transaction, so captures do not change the
 * transaction-level maximum). The minimum refund is configured in MAJOR units
 * (Flutterwave charges major units), so no minor-to-major conversion is applied.
 *
 * Refund records here come from the local Shopware repositories (see the admin
 * detail component), so a refund carries its amount as a price object
 * ({ totalPrice }) and its status as a state-machine technical name. The helpers
 * below also accept the older Flutterwave API shape ({ status, amount_refunded })
 * so both forms are handled uniformly.
 */

const CURRENCY_DECIMALS = {
    JPY: 0, XOF: 0, XAF: 0, KMF: 0, GNF: 0, RWF: 0, UGX: 0,
    BHD: 3, IQD: 3, JOD: 3, KWD: 3, LYD: 3, OMR: 3, TND: 3,
};

/**
 * Refund states that free a refund's amount back into the refundable balance;
 * everything else consumes balance. A denylist (not an allowlist of active
 * states) so an unrecognised state cannot make the UI offer an over-refund.
 * Covers both the Shopware refund states ('failed', 'cancelled') and the
 * Flutterwave API status ('failed'). Mirrors the backend guard.
 */
const REFUND_FREED_STATUSES = ['failed', 'cancelled'];

/**
 * A refund's status, from either a local Shopware record (state machine) or a
 * Flutterwave API object, lower-cased.
 *
 * @param {Object} refund
 * @returns {string}
 */
function refundStatus(refund) {
    return String(
        refund?.stateMachineState?.technicalName ?? refund?.status ?? ''
    ).toLowerCase();
}

/**
 * A refund's amount, from either a local Shopware record (price object) or a
 * Flutterwave API object (amount_refunded / amount).
 *
 * @param {Object} refund
 * @returns {number}
 */
function refundAmount(refund) {
    const amount = refund?.amount;

    if (amount && typeof amount === 'object') {
        return Number(amount.totalPrice ?? 0);
    }

    return Number(refund?.amount_refunded ?? amount ?? 0);
}

/**
 * Number of minor-unit decimals for a currency (defaults to 2).
 *
 * @param {string} currency
 * @returns {number}
 */
export function currencyDecimals(currency) {
    return CURRENCY_DECIMALS[currency] ?? 2;
}

/**
 * Rounds a major-unit amount to its currency's precision.
 *
 * @param {number} value
 * @param {string} currency
 * @returns {number}
 */
export function roundToCurrency(value, currency) {
    return Number(Number(value ?? 0).toFixed(currencyDecimals(currency)));
}

/**
 * Minimum refundable amount in major units. Flutterwave's configured minimum is
 * already major, so this only normalises precision.
 *
 * @param {number} configuredMajor
 * @param {string} currency
 * @returns {number}
 */
export function minRefundableAmount(configuredMajor, currency) {
    return roundToCurrency(configuredMajor ?? 0, currency);
}

/**
 * Sum of refund amounts still consuming balance. Accepts local Shopware refund
 * records or Flutterwave API refund objects.
 *
 * @param {Array} refunds
 * @returns {number}
 */
export function sumActiveRefunds(refunds = []) {
    return refunds.reduce((total, refund) => {
        if (REFUND_FREED_STATUSES.includes(refundStatus(refund))) {
            return total;
        }

        return total + refundAmount(refund);
    }, 0);
}

/**
 * Maximum refundable amount in major units: the charged amount minus refunds
 * already made.
 *
 * @param {Object} params
 * @param {number} params.transactionAmount charged amount (major units)
 * @param {Array}  [params.refunds] refund records (local Shopware or API shape)
 * @param {string} params.currency
 * @returns {number}
 */
export function maxRefundableAmount({ transactionAmount, refunds = [], currency }) {
    const refunded = sumActiveRefunds(refunds);

    return roundToCurrency(Math.max(0, Number(transactionAmount ?? 0) - refunded), currency);
}
