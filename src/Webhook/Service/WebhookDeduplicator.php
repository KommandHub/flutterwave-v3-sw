<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Service;

use Kommandhub\FlutterwaveSW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveSW\Util\FlutterwaveConstants;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

/**
 * Records which webhook deliveries have already been applied to a transaction.
 *
 * Flutterwave retries a webhook until it sees a 200, so the same event arrives
 * more than once as a matter of course — and because `verif-hash` is a static
 * secret rather than a per-payload signature, a replay is indistinguishable from
 * a genuine redelivery. Every state-changing subscriber therefore claims its
 * event here first; a second delivery finds the key already present and stops.
 *
 * The marks live in the order transaction's custom fields
 * (FlutterwaveConstants::FIELD_PROCESSED_EVENTS) so they share the transaction's
 * lifetime and need no extra table.
 *
 * Note this is a last-writer-wins store, not a lock: two *simultaneous*
 * deliveries could both read "unprocessed" before either writes. The subscribers
 * are additionally idempotent against Shopware state (a refund already completed
 * is not completed twice), so a race degrades to a no-op rather than a double
 * mutation.
 */
class WebhookDeduplicator
{
    public function __construct(private readonly OrderTransactionService $orderTransactionService)
    {
    }

    /**
     * Whether this event has already been applied to the transaction.
     */
    public function isProcessed(OrderTransactionEntity $transaction, string $eventKey): bool
    {
        return in_array($eventKey, $this->processedEvents($transaction), true);
    }

    /**
     * Marks the event as applied. Safe to call repeatedly.
     */
    public function markProcessed(OrderTransactionEntity $transaction, string $eventKey, Context $context): void
    {
        $processed = $this->processedEvents($transaction);

        if (in_array($eventKey, $processed, true)) {
            return;
        }

        $processed[] = $eventKey;

        $customFields = $transaction->getCustomFields() ?? [];
        $customFields[FlutterwaveConstants::FIELD_PROCESSED_EVENTS] = $processed;

        $this->orderTransactionService->update([
            [
                'id' => $transaction->getId(),
                'customFields' => $customFields,
            ],
        ], $context);

        // Keep the in-memory entity consistent, so a second check within the
        // same request sees the mark without a re-read.
        $transaction->setCustomFields($customFields);
    }

    /**
     * Builds a stable key for one delivery. Includes the event name so two
     * different events about the same object cannot collide.
     */
    public function buildKey(string $eventName, string $identifier): string
    {
        return $eventName . ':' . $identifier;
    }

    /**
     * @return array<int, string>
     */
    private function processedEvents(OrderTransactionEntity $transaction): array
    {
        $processed = $transaction->getCustomFields()[FlutterwaveConstants::FIELD_PROCESSED_EVENTS] ?? [];

        if (!is_array($processed)) {
            return [];
        }

        return array_values(array_filter($processed, 'is_string'));
    }
}
