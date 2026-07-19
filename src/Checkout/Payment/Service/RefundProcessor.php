<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service;

use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;

/**
 * @final
 */
readonly class RefundProcessor
{
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private OrderTransactionCaptureRefundStateHandler $refundStateHandler,
        private OrderTransactionCaptureStateHandler $captureStateHandler,
        private OrderTransactionStateHandler $transactionStateHandler,
        private RefundAggregator $aggregator,
        private Connection $connection
    ) {
    }

    /**
     * Processes a refund by updating the states of the refund, its associated capture,
     * and the overall order transaction based on the aggregated refund amounts.
     *
     * @param RefundPaymentTransactionStruct $transaction The refund transaction data.
     * @param Context $context The current execution context.
     *
     * @throws PaymentException|\Throwable If the refund identifier is missing or the refund cannot be found.
     */
    public function process(RefundPaymentTransactionStruct $transaction, Context $context): void
    {
        $orderTransactionId = $transaction->getOrderTransactionId();
        $refundId = $transaction->getRefundId();

        if (!$refundId) {
            throw PaymentException::refundInterrupted(
                $orderTransactionId,
                'Missing refund identifier.'
            );
        }

        $orderTransaction = $this->orderTransactionService->getOrderTransaction($orderTransactionId, $context);

        $refund = $this->findRefund($orderTransaction, $refundId);

        if (!$refund) {
            throw PaymentException::refundInterrupted(
                $orderTransactionId,
                'Refund not found.'
            );
        }

        $this->connection->transactional(function () use (
            $orderTransaction,
            $refund,
            $orderTransactionId,
            $context
        ) {
            if ($refund->getStateMachineState()?->getTechnicalName() === OrderTransactionCaptureRefundStates::STATE_FAILED) {
                $this->refundStateHandler->reopen($refund->getId(), $context);
            }

            $this->refundStateHandler->complete($refund->getId(), $context);

            $result = $this->aggregator->aggregate($orderTransaction, $refund->getId());

            /** @var array<string, \stdClass&object{isFullyRefunded: bool}> $capturesData */
            $capturesData = $result->captures;

            foreach ($capturesData as $captureId => $captureData) {
                if ($captureData->isFullyRefunded) {
                    $captures = $orderTransaction->getCaptures();
                    $captureEntity = $captures?->get($captureId);

                    if ($captureEntity && $captureEntity->getStateMachineState()?->getTechnicalName() === OrderTransactionCaptureStates::STATE_FAILED) {
                        $this->captureStateHandler->reopen($captureId, $context);
                    }

                    $this->captureStateHandler->complete($captureId, $context);
                }
            }

            if ($orderTransaction->getStateMachineState()?->getTechnicalName() === OrderTransactionStates::STATE_FAILED) {
                $this->transactionStateHandler->reopen($orderTransactionId, $context);
            }

            if ($result->isFullyRefunded) {
                $this->transactionStateHandler->refund($orderTransactionId, $context);
            } else {
                $this->transactionStateHandler->refundPartially($orderTransactionId, $context);
            }
        });
    }

    /**
     * Finds a specific refund by its ID within the captures of an order transaction.
     *
     * @param OrderTransactionEntity $orderTransaction The order transaction entity.
     * @param string $refundId The ID of the refund to find.
     *
     * @return OrderTransactionCaptureRefundEntity|null The refund entity if found, otherwise null.
     */
    private function findRefund(OrderTransactionEntity $orderTransaction, string $refundId): ?OrderTransactionCaptureRefundEntity
    {
        $captures = $orderTransaction->getCaptures();

        if ($captures === null) {
            return null;
        }

        foreach ($captures as $capture) {
            $refunds = $capture->getRefunds();

            if ($refunds === null) {
                continue; // @codeCoverageIgnore
            }

            foreach ($refunds as $refund) {
                if ($refund->getId() === $refundId) {
                    return $refund;
                }
            }
        }

        return null;
    }
}
