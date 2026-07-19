<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Checkout\Payment\Service;

use Kommandhub\FlutterwaveSW\Client\FlutterwaveClient;
use Kommandhub\FlutterwaveSW\Util\FlutterwaveCurrencyHelper;

/**
 * Flutterwave-API-backed implementation of {@see FlutterwaveRefundLedgerInterface}.
 *
 * @final
 */
final readonly class FlutterwaveRefundLedger implements FlutterwaveRefundLedgerInterface
{
    /**
     * The only Flutterwave refund status that frees a refund's amount back
     * into the refundable balance. Every other status — completed,
     * successful, pending, or one Flutterwave adds later — is treated as
     * still consuming balance.
     *
     * This is a deliberate denylist rather than an allowlist of "active"
     * statuses: an allowlist that omitted a real status (e.g. "successful",
     * which Flutterwave uses alongside "completed") would under-count what
     * has already been refunded and let the guard authorise an over-refund.
     * Failing safe on money means counting anything not explicitly failed.
     */
    private const REFUND_FREED_STATUS = 'failed';

    public function __construct(private FlutterwaveClient $flutterwave)
    {
    }

    /**
     * {@inheritDoc}
     *
     * Flutterwave has no endpoint that returns only a single transaction's
     * refunds: `GET /refunds?id=` is documented to filter by transaction id,
     * but in practice the account-wide list comes back unfiltered. Relying on
     * the server filter alone leaked every account refund into the order
     * view — and, worse, into the over-refund guard, where foreign refunds
     * would wrongly shrink the refundable balance.
     *
     * So the list is always filtered client-side on each refund's `tx_id`,
     * which the refund object carries and which equals our stored
     * transaction id. This is authoritative regardless of whether the server
     * honours the query param.
     */
    public function refundsForTransaction(string $flutterwaveTransactionId, ?string $salesChannelId): array
    {
        $response = $this->flutterwave->transactions()->refunds($flutterwaveTransactionId, $salesChannelId);
        $refunds = is_array($response['data'] ?? null) ? $response['data'] : [];

        $matching = [];

        foreach ($refunds as $refund) {
            if (!is_array($refund)) {
                continue;
            }

            $txId = $refund['tx_id'] ?? null;

            if (is_numeric($txId) && (string)$txId === $flutterwaveTransactionId) {
                $matching[] = $refund;
            }
        }

        return $matching;
    }

    public function alreadyRefundedMinor(
        string $flutterwaveTransactionId,
        string $currencyIso,
        ?string $salesChannelId
    ): int {
        $refunds = $this->refundsForTransaction($flutterwaveTransactionId, $salesChannelId);

        $total = 0;

        foreach ($refunds as $refund) {
            $status = is_string($refund['status'] ?? null) ? strtolower($refund['status']) : '';

            // Count every refund that has not explicitly failed (see the
            // REFUND_FREED_STATUS note), so an unrecognised status cannot
            // open an over-refund.
            if ($status === self::REFUND_FREED_STATUS) {
                continue;
            }

            // Flutterwave reports the refunded value as `amount_refunded`.
            $amount = $refund['amount_refunded'] ?? $refund['amount'] ?? 0;

            if (is_numeric($amount)) {
                $total += FlutterwaveCurrencyHelper::toMinorUnit((float)$amount, $currencyIso);
            }
        }

        return $total;
    }
}
