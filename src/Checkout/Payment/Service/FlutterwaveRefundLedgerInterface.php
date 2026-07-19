<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service;

use Kommandhub\FlutterwaveV3SW\Exception\FlutterwaveException;

/**
 * Reads Flutterwave's own refund records for a transaction.
 *
 * This plugin keeps local refund entities (see
 * {@see \Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService::createRefund()})
 * but treats them as a local cache of intent, not the source of truth for
 * "how much of this transaction has been refunded" — Flutterwave's own
 * refund list is authoritative, since a refund can also be raised outside
 * this shop (the Flutterwave dashboard, or another integration on the same
 * account) and would never appear locally. The over-refund guard in
 * {@see RefundAmountCalculator} is only as safe as this data, so both refund
 * creation and the admin refund-history panel read through the same
 * implementation of this interface rather than each calling the Flutterwave
 * client directly.
 *
 * Extracted as an interface — mirroring
 * {@see \Kommandhub\FlutterwaveV3SW\Client\Http\HttpClientInterface} — so
 * callers (the refund controller, tests) depend on "a source of refund
 * history" rather than on the concrete Flutterwave HTTP integration, and so
 * `FlutterwaveRefundLedger` can be replaced by a caching or locally-backed
 * implementation later without touching its callers.
 */
interface FlutterwaveRefundLedgerInterface
{
    /**
     * Refunds already raised against one Flutterwave transaction.
     *
     * @param string $flutterwaveTransactionId Flutterwave's numeric transaction id.
     * @param string|null $salesChannelId Resolves which API credentials to use.
     *
     * @return array<int, array<string, mixed>> Refund objects for this transaction only.
     *
     * @throws FlutterwaveException When the refund list cannot be loaded.
     */
    public function refundsForTransaction(string $flutterwaveTransactionId, ?string $salesChannelId): array;

    /**
     * Sum of this transaction's refunds that already consume refundable
     * balance, in minor units.
     *
     * @throws FlutterwaveException When the refund list cannot be loaded.
     */
    public function alreadyRefundedMinor(
        string $flutterwaveTransactionId,
        string $currencyIso,
        ?string $salesChannelId
    ): int;
}
