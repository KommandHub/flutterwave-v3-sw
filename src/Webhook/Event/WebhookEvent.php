<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Event;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for a decoded Flutterwave webhook.
 *
 * V3 payloads share the shape `{ "event": "...", "data": { ... } }`.
 *
 * @see https://developer.flutterwave.com/v3.0.0/docs/webhooks#structure-of-a-webhook-payload
 *
 * The `data` here is UNTRUSTED: Flutterwave's verif-hash covers the sender, not
 * the body (see WebhookSignatureValidator). Subscribers use it only to identify
 * which transaction the notification is about, then re-fetch authoritative state
 * from the API.
 */
abstract class WebhookEvent extends Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        protected readonly array $data,
        protected readonly Context $context
    ) {
    }

    /**
     * The Flutterwave `event` value this class handles.
     */
    abstract public static function getWebhookName(): string;

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Reads a scalar from the payload as a string, or null when absent/unusable.
     *
     * Flutterwave is inconsistent about casing between its REST API and its
     * webhooks (`tx_id` vs `TransactionId`), so callers pass every spelling they
     * accept and take the first that matches.
     */
    protected function scalar(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->data[$key] ?? null;

            if (is_scalar($value) && (string)$value !== '') {
                return (string)$value;
            }
        }

        return null;
    }
}
