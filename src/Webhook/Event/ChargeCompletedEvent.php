<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Event;

/**
 * `charge.completed` — fires for both successful and failed payments, so the
 * status must be read rather than assumed.
 */
class ChargeCompletedEvent extends WebhookEvent
{
    public static function getWebhookName(): string
    {
        return 'charge.completed';
    }

    /**
     * Flutterwave's numeric transaction id (`data.id`), which addresses the
     * verify endpoint.
     */
    public function getFlutterwaveTransactionId(): ?string
    {
        return $this->scalar('id');
    }

    /**
     * The merchant reference we generated on initialize (`tx_ref`).
     */
    public function getReference(): ?string
    {
        return $this->scalar('tx_ref');
    }
}
