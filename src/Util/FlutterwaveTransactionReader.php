<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Util;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * Reads Flutterwave-specific metadata back off an {@see OrderTransactionEntity}'s
 * custom fields.
 *
 * `FinalizeProcessor` is the only writer of these fields (see
 * {@see FlutterwaveConstants} for the key list); this class is the read-side
 * counterpart, factored out so the admin refund endpoint and the refund
 * history endpoint interpret the same stored data the same way instead of
 * each re-implementing the lookup and its edge cases.
 *
 * Follows the same static-resolver shape as {@see OrderCurrencyResolver}:
 * both are pure reads over an already-loaded entity, so there is nothing to
 * inject and no state to hold.
 */
final class FlutterwaveTransactionReader
{
    /**
     * The Flutterwave transaction id used to address the refund and
     * refund-list endpoints (`/transactions/{id}/refund`, `/refunds?id=`).
     *
     * Flutterwave transaction ids are always numeric. The Shopware
     * order_transaction id is a 32-character hex UUID, so requiring a
     * numeric value here makes it impossible to accidentally send the
     * Shopware id to Flutterwave — a UUID (or any other non-numeric value
     * that a bad finalize or legacy data might have left in the custom
     * field) is rejected rather than used as the refund target.
     *
     * @return string|null The numeric id as a string, or null when absent or
     *                     not numeric.
     */
    public static function transactionId(OrderTransactionEntity $transaction): ?string
    {
        $id = $transaction->getCustomFields()[FlutterwaveConstants::FIELD_TRANSACTION_ID] ?? null;

        return is_numeric($id) ? (string)$id : null;
    }

    /**
     * The amount Flutterwave actually charged, in major units.
     *
     * @return float The charged amount, falling back to the Shopware
     *               transaction total when the custom field is missing (e.g.
     *               data written before this field existed), so a refund is
     *               never blocked by absent metadata.
     */
    public static function chargedAmount(OrderTransactionEntity $transaction): float
    {
        $charged = $transaction->getCustomFields()[FlutterwaveConstants::FIELD_AMOUNT_CHARGED] ?? null;

        if (is_numeric($charged)) {
            return (float)$charged;
        }

        return $transaction->getAmount()->getTotalPrice();
    }
}
