<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Subscriber;

use Kommandhub\FlutterwaveSW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\FlutterwaveSW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveSW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveSW\Webhook\Event\ChargeCompletedEvent;
use Kommandhub\FlutterwaveSW\Webhook\Service\WebhookDeduplicator;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

/**
 * Settles a payment from a `charge.completed` webhook.
 *
 * This is the safety net for the redirect flow: if the customer closes the tab
 * before returning to the shop, finalize never runs and the order would sit
 * unpaid forever. The webhook carries the payment to completion regardless.
 *
 * Nothing in the payload is trusted. It supplies only two identifiers — which
 * order transaction (`tx_ref`) and which Flutterwave transaction (`id`) — and
 * FinalizeProcessor then re-verifies against the Flutterwave API and re-checks
 * amount and currency before any state changes. That is what makes the static,
 * non-payload-binding verif-hash acceptable here.
 */
final readonly class ChargeCompletedSubscriber
{
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private FinalizeProcessor $finalizeProcessor,
        private WebhookDeduplicator $deduplicator,
        private ConfigurableLogger $logger
    ) {
    }

    #[AsEventListener(ChargeCompletedEvent::class)]
    public function __invoke(ChargeCompletedEvent $event): void
    {
        $context = $event->getContext();

        // tx_ref is the reference this plugin generated on initialize, and it is
        // the Shopware order transaction id (see PayloadBuilder).
        $orderTransactionId = $event->getReference();
        $flutterwaveTransactionId = $event->getFlutterwaveTransactionId();

        if ($orderTransactionId === null || $flutterwaveTransactionId === null) {
            $this->logger->warning('[Flutterwave] charge.completed webhook is missing tx_ref or id.');

            return;
        }

        // The reference must look like a Shopware id before it is used as one.
        // Flutterwave delivers every charge on the account, including those from
        // other integrations whose tx_ref is not a UUID at all — passing one to
        // the DAL throws, which would surface as a 500 and make Flutterwave retry
        // a delivery that can never succeed.
        if (!Uuid::isValid($orderTransactionId)) {
            $this->logger->info('[Flutterwave] charge.completed webhook has a tx_ref that is not a Shopware id; ignoring.', [
                'tx_ref' => $orderTransactionId,
            ]);

            return;
        }

        try {
            $transaction = $this->orderTransactionService->getOrderTransaction($orderTransactionId, $context);
        } catch (\InvalidArgumentException) {
            // Not ours — another integration on the same Flutterwave account, or
            // a reference from a different shop. Acknowledge and ignore.
            $this->logger->info('[Flutterwave] charge.completed webhook references an unknown transaction.', [
                'tx_ref' => $orderTransactionId,
            ]);

            return;
        }

        $salesChannelId = $transaction->getOrder()?->getSalesChannelId();
        $eventKey = $this->deduplicator->buildKey(ChargeCompletedEvent::getWebhookName(), $flutterwaveTransactionId);

        if ($this->deduplicator->isProcessed($transaction, $eventKey)) {
            $this->logger->info('[Flutterwave] charge.completed webhook already processed; ignoring redelivery.', [
                ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
                'orderTransactionId' => $orderTransactionId,
            ]);

            return;
        }

        // Second idempotency layer, independent of the dedup marks: a payment the
        // redirect flow already settled must not be re-settled.
        if ($transaction->getStateMachineState()?->getTechnicalName() === OrderTransactionStates::STATE_PAID) {
            $this->deduplicator->markProcessed($transaction, $eventKey, $context);

            return;
        }

        // Only the transaction id is handed over: the status is deliberately not
        // forwarded so the payload cannot steer the outcome. FinalizeProcessor
        // re-fetches the real status from the API.
        $request = new Request(['transaction_id' => $flutterwaveTransactionId]);

        try {
            $this->finalizeProcessor->process(
                $request,
                new PaymentTransactionStruct($orderTransactionId),
                $context
            );
        } catch (\Throwable $exception) {
            // A declined payment is a legitimate outcome, not a delivery failure:
            // FinalizeProcessor has already moved the transaction to failed.
            // Swallow so the webhook is acknowledged and Flutterwave stops
            // retrying an event that will never succeed.
            $this->logger->warning('[Flutterwave] charge.completed webhook did not settle the payment.', [
                ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
                'orderTransactionId' => $orderTransactionId,
                'exception' => $exception,
            ]);
        }

        $this->deduplicator->markProcessed($transaction, $eventKey, $context);
    }
}
