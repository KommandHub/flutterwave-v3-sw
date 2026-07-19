<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Subscriber;

use Kommandhub\FlutterwaveSW\Checkout\Payment\Service\RefundProcessor;
use Kommandhub\FlutterwaveSW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveSW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveSW\Webhook\Event\RefundCompletedEvent;
use Kommandhub\FlutterwaveSW\Webhook\Service\WebhookDeduplicator;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Completes a pending refund from a `refund.completed` webhook.
 *
 * The admin refund action creates the Shopware capture and refund in their
 * initial (pending) state and records the Flutterwave refund id on the refund's
 * `externalReference`. Flutterwave refunds are asynchronous — "refunds initiated
 * usually take between 3-15 working days to be completed" — so the local record
 * stays pending until Flutterwave confirms the outcome here.
 *
 * Correlation is by that stored refund id, which is why nothing else in the
 * payload needs to be trusted to find the right record.
 */
final readonly class RefundCompletedSubscriber
{
    /**
     * Flutterwave marks a settled refund `completed`; some accounts report
     * `successful`. Anything else that is not an explicit failure is still in
     * flight and leaves the record pending.
     */
    private const SUCCESS_STATUSES = ['completed', 'successful'];
    private const FAILURE_STATUSES = ['failed'];

    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private OrderTransactionCaptureRefundStateHandler $refundStateHandler,
        private RefundProcessor $refundProcessor,
        private WebhookDeduplicator $deduplicator,
        private ConfigurableLogger $logger
    ) {
    }

    #[AsEventListener(RefundCompletedEvent::class)]
    public function __invoke(RefundCompletedEvent $event): void
    {
        $context = $event->getContext();
        $flutterwaveRefundId = $event->getRefundId();

        if ($flutterwaveRefundId === null) {
            $this->logger->warning('[Flutterwave] refund.completed webhook is missing the refund id.');

            return;
        }

        $refund = $this->orderTransactionService->findRefundByExternalReference($flutterwaveRefundId, $context);

        if ($refund === null) {
            // A refund raised outside this shop (the Flutterwave dashboard, or
            // another integration on the same account). Acknowledge and ignore.
            $this->logger->info('[Flutterwave] refund.completed webhook has no matching pending refund.', [
                'flutterwaveRefundId' => $flutterwaveRefundId,
            ]);

            return;
        }

        $transaction = $refund->getTransactionCapture()?->getTransaction();
        $status = $event->getStatus();

        if ($transaction !== null) {
            $eventKey = $this->deduplicator->buildKey(RefundCompletedEvent::getWebhookName(), $flutterwaveRefundId);

            if ($this->deduplicator->isProcessed($transaction, $eventKey)) {
                $this->logger->info('[Flutterwave] refund.completed webhook already processed; ignoring redelivery.', [
                    'flutterwaveRefundId' => $flutterwaveRefundId,
                ]);

                return;
            }
        }

        // Independent of the dedup marks: a refund already in a final state must
        // not be transitioned again, or the state machine throws.
        $currentState = $refund->getStateMachineState()?->getTechnicalName();

        if (in_array($currentState, [
            OrderTransactionCaptureRefundStates::STATE_COMPLETED,
            OrderTransactionCaptureRefundStates::STATE_FAILED,
            OrderTransactionCaptureRefundStates::STATE_CANCELLED,
        ], true)) {
            $this->logger->info('[Flutterwave] refund is already final; ignoring webhook.', [
                'flutterwaveRefundId' => $flutterwaveRefundId,
                'state' => $currentState,
            ]);

            return;
        }

        $transactionCapture = $refund->getTransactionCapture();

        if ($transactionCapture === null) {
            $this->logger->warning('[Flutterwave] refund.completed webhook: refund has no associated transaction capture.', [
                'flutterwaveRefundId' => $flutterwaveRefundId,
                'refundId' => $refund->getId(),
            ]);

            return;
        }

        if (in_array($status, self::SUCCESS_STATUSES, true)) {
            $this->refundProcessor->process(
                new RefundPaymentTransactionStruct(
                    $transactionCapture->getOrderTransactionId(),
                    $refund->getId()
                ),
                $context
            );

            $this->logger->info('[Flutterwave] Refund completed via webhook.', [
                'flutterwaveRefundId' => $flutterwaveRefundId,
                'refundId' => $refund->getId(),
            ]);
        } elseif (in_array($status, self::FAILURE_STATUSES, true)) {
            $this->refundStateHandler->fail($refund->getId(), $context);

            $this->logger->warning('[Flutterwave] Refund failed via webhook.', [
                'flutterwaveRefundId' => $flutterwaveRefundId,
                'refundId' => $refund->getId(),
            ]);
        } else {
            // Still in flight. Leave it pending and do NOT mark the event
            // processed, so a later delivery with a final status is still acted
            // on.
            $this->logger->info('[Flutterwave] Refund webhook reports a non-final status; leaving it pending.', [
                'flutterwaveRefundId' => $flutterwaveRefundId,
                'status' => $status,
            ]);

            return;
        }

        if ($transaction !== null) {
            $this->deduplicator->markProcessed(
                $transaction,
                $this->deduplicator->buildKey(RefundCompletedEvent::getWebhookName(), $flutterwaveRefundId),
                $context
            );
        }
    }
}
