<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Service;

use Kommandhub\FlutterwaveSW\Webhook\Event\ChargeCompletedEvent;
use Kommandhub\FlutterwaveSW\Webhook\Event\RefundCompletedEvent;
use Kommandhub\FlutterwaveSW\Webhook\Event\WebhookEvent;
use Shopware\Core\Framework\Context;

/**
 * Maps a Flutterwave `event` name onto the typed event this plugin dispatches.
 *
 * Adding support for a new Flutterwave event is a new WebhookEvent subclass plus
 * one line here — subscribers bind to the class, not to a string.
 *
 * Events Flutterwave can send that are deliberately absent (transfer.completed,
 * subscription.cancelled, bvn.completed, singlebillpayment.status) resolve to
 * null and are acknowledged without action: this plugin does not make payouts,
 * sell subscriptions or pay bills, so acting on them would be meaningless.
 */
class WebhookEventFactory
{
    /**
     * @var array<string, class-string<WebhookEvent>>
     */
    private const EVENT_MAP = [
        'charge.completed' => ChargeCompletedEvent::class,
        'refund.completed' => RefundCompletedEvent::class,
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function create(string $eventName, array $data, Context $context): ?WebhookEvent
    {
        $class = self::EVENT_MAP[strtolower($eventName)] ?? null;

        if ($class === null) {
            return null;
        }

        return new $class($data, $context);
    }

    /**
     * Whether the plugin acts on this Flutterwave event.
     */
    public function supports(string $eventName): bool
    {
        return isset(self::EVENT_MAP[strtolower($eventName)]);
    }
}
