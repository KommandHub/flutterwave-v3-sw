<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Checkout\Payment\Service;

use Doctrine\DBAL\Connection;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundAggregationResult;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundAggregator;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundProcessor;
use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class RefundProcessorTest extends TestCase
{
    private OrderTransactionService|\PHPUnit\Framework\MockObject\MockObject $orderTransactionService;
    private OrderTransactionCaptureRefundStateHandler|\PHPUnit\Framework\MockObject\MockObject $refundStateHandler;
    private OrderTransactionCaptureStateHandler|\PHPUnit\Framework\MockObject\MockObject $captureStateHandler;
    private OrderTransactionStateHandler|\PHPUnit\Framework\MockObject\MockObject $transactionStateHandler;
    private RefundAggregator|\PHPUnit\Framework\MockObject\MockObject $aggregator;
    private Connection|\PHPUnit\Framework\MockObject\MockObject $connection;
    private RefundProcessor $processor;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->refundStateHandler = $this->createMock(OrderTransactionCaptureRefundStateHandler::class);
        $this->captureStateHandler = $this->createMock(OrderTransactionCaptureStateHandler::class);
        $this->transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->aggregator = $this->createMock(RefundAggregator::class);
        $this->connection = $this->createMock(Connection::class);

        // Simple mock for transactional
        $this->connection->method('transactional')->willReturnCallback(fn ($callback) => $callback($this->connection));

        $this->processor = new RefundProcessor(
            $this->orderTransactionService,
            $this->refundStateHandler,
            $this->captureStateHandler,
            $this->transactionStateHandler,
            $this->aggregator,
            $this->connection
        );
    }

    public function testProcessSuccess(): void
    {
        $context = Context::createDefaultContext();
        $refundStruct = new RefundPaymentTransactionStruct('refund-1', 'transaction-1');

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('transaction-1');

        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId('refund-1');
        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_OPEN);
        $refund->setStateMachineState($state);

        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-1');
        $captureState = new StateMachineStateEntity();
        $captureState->setTechnicalName('open');
        $capture->setStateMachineState($captureState);
        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));

        $orderTransaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));
        $orderTransactionState = new StateMachineStateEntity();
        $orderTransactionState->setTechnicalName(OrderTransactionStates::STATE_OPEN);
        $orderTransaction->setStateMachineState($orderTransactionState);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $aggregationResult = new RefundAggregationResult(
            ['capture-1' => (object)['isFullyRefunded' => true]],
            true
        );
        $this->aggregator->method('aggregate')->willReturn($aggregationResult);

        $this->refundStateHandler->expects(static::once())->method('complete')->with('refund-1', $context);
        $this->captureStateHandler->expects(static::once())->method('complete')->with('capture-1', $context);
        $this->transactionStateHandler->expects(static::once())->method('refund')->with('transaction-1', $context);

        $this->processor->process($refundStruct, $context);
    }

    public function testProcessWithReopens(): void
    {
        $context = Context::createDefaultContext();
        $refundStruct = new RefundPaymentTransactionStruct('refund-1', 'transaction-1');

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('transaction-1');
        $orderTransactionState = new StateMachineStateEntity();
        $orderTransactionState->setTechnicalName(OrderTransactionStates::STATE_FAILED);
        $orderTransaction->setStateMachineState($orderTransactionState);

        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId('refund-1');
        $refundState = new StateMachineStateEntity();
        $refundState->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_FAILED);
        $refund->setStateMachineState($refundState);

        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-1');
        $captureState = new StateMachineStateEntity();
        $captureState->setTechnicalName(OrderTransactionCaptureStates::STATE_FAILED);
        $capture->setStateMachineState($captureState);
        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));

        $orderTransaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $aggregationResult = new RefundAggregationResult(
            ['capture-1' => (object)['isFullyRefunded' => true]],
            true
        );
        $this->aggregator->method('aggregate')->willReturn($aggregationResult);

        $this->refundStateHandler->expects(static::once())->method('reopen');
        $this->refundStateHandler->expects(static::once())->method('complete');
        $this->captureStateHandler->expects(static::once())->method('reopen');
        $this->captureStateHandler->expects(static::once())->method('complete');
        $this->transactionStateHandler->expects(static::once())->method('reopen');
        $this->transactionStateHandler->expects(static::once())->method('refund');

        $this->processor->process($refundStruct, $context);
    }

    public function testProcessThrowsIfRefundIdMissing(): void
    {
        $context = Context::createDefaultContext();
        $refundStruct = new RefundPaymentTransactionStruct('', 'transaction-1');

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Missing refund identifier.');

        $this->processor->process($refundStruct, $context);
    }

    public function testProcessThrowsIfRefundNotFound(): void
    {
        $context = Context::createDefaultContext();
        $refundStruct = new RefundPaymentTransactionStruct('refund-1', 'transaction-1');

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('transaction-1');
        $orderTransaction->setCaptures(new OrderTransactionCaptureCollection([]));

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Refund not found.');

        $this->processor->process($refundStruct, $context);
    }

    public function testProcessPartialRefund(): void
    {
        $context = Context::createDefaultContext();
        $refundStruct = new RefundPaymentTransactionStruct('refund-1', 'transaction-1');

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('transaction-1');

        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId('refund-1');
        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_OPEN);
        $refund->setStateMachineState($state);

        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-1');
        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));

        $orderTransaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $aggregationResult = new RefundAggregationResult(
            ['capture-1' => (object)['isFullyRefunded' => false]],
            false
        );
        $this->aggregator->method('aggregate')->willReturn($aggregationResult);

        $this->transactionStateHandler->expects(static::once())->method('refundPartially');

        $this->processor->process($refundStruct, $context);
    }

    public function testFindRefundWithNullCaptures(): void
    {
        $context = Context::createDefaultContext();
        $refundStruct = new RefundPaymentTransactionStruct('refund-1', 'transaction-1');

        // Use reflection to set captures to null if setter enforces type
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('transaction-1');

        $reflection = new \ReflectionClass($orderTransaction);
        $property = $reflection->getProperty('captures');
        $property->setValue($orderTransaction, null);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Refund not found.');

        $this->processor->process($refundStruct, $context);
    }
}
