<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Event;

/**
 * `refund.completed` — fires when a refund settles or fails.
 *
 * Two delivery paths land here and their payloads differ in casing:
 *  - the account webhook, which Flutterwave only sends once support enables
 *    refund webhooks on the account, and
 *  - the per-refund `callbackurl` passed on POST /transactions/{id}/refund,
 *    which works without that flag.
 *
 * The refund payload uses PascalCase (`TransactionId`, `AmountRefunded`) where
 * the REST API uses snake_case (`tx_id`, `amount_refunded`), so both spellings
 * are accepted.
 *
 * @see https://developer.flutterwave.com/v3.0/docs/refunds
 */
class RefundCompletedEvent extends WebhookEvent
{
    public static function getWebhookName(): string
    {
        return 'refund.completed';
    }

    /**
     * The Flutterwave refund id — the correlation key stored as the Shopware
     * refund's externalReference when the refund was created.
     */
    public function getRefundId(): ?string
    {
        return $this->scalar('id');
    }

    /**
     * The transaction this refund belongs to.
     */
    public function getFlutterwaveTransactionId(): ?string
    {
        return $this->scalar('TransactionId', 'tx_id', 'transaction_id');
    }

    public function getStatus(): ?string
    {
        $status = $this->scalar('status');

        return $status !== null ? strtolower($status) : null;
    }
}
