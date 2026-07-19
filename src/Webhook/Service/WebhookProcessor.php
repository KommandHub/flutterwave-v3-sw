<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Webhook\Service;

use Kommandhub\FlutterwaveV3SW\Logging\ConfigurableLogger;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Turns a raw Flutterwave webhook request into a dispatched, typed event.
 *
 * Validates authenticity, parses the V3 envelope `{ event, data }`, and hands
 * off to a subscriber. Knows nothing about payments — new event types need no
 * change here.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly WebhookSignatureValidator $signatureValidator,
        private readonly WebhookEventFactory $eventFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConfigurableLogger $logger
    ) {
    }

    /**
     * @throws AccessDeniedHttpException when the request is not from Flutterwave
     * @throws BadRequestHttpException when the payload is not a usable V3 envelope
     */
    public function process(Request $request, Context $context): void
    {
        $this->signatureValidator->validate($request);

        $payload = json_decode((string)$request->getContent(), true);

        if (!is_array($payload)) {
            throw new BadRequestHttpException('Webhook payload is not valid JSON.');
        }

        $eventName = $payload['event'] ?? null;

        if (!is_string($eventName) || $eventName === '') {
            throw new BadRequestHttpException('Webhook payload has no event name.');
        }

        $data = $payload['data'] ?? null;

        if (!is_array($data)) {
            throw new BadRequestHttpException('Webhook payload has no data object.');
        }

        /** @var array<string, mixed> $data */
        $event = $this->eventFactory->create($eventName, $data, $context);

        if ($event === null) {
            // Not an error: Flutterwave sends account-wide events this plugin has
            // no business acting on. Acknowledge so it stops retrying.
            $this->logger->info('[Flutterwave] Ignoring unhandled webhook event.', [
                'event' => $eventName,
            ]);

            return;
        }

        $this->logger->info('[Flutterwave] Dispatching webhook event.', [
            'event' => $eventName,
        ]);

        $this->eventDispatcher->dispatch($event);
    }
}
