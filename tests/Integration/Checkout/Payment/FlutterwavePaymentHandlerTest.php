<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Integration\Checkout\Payment;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\FlutterwavePaymentHandler;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\PaymentProcessor;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundProcessor;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Struct\FlutterwaveInitializationResponse;
use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The handler is a thin delegator: each Shopware entry point forwards to the
 * matching processor. The payment behaviour itself is covered by the processors'
 * own tests, so this only pins the delegation contract.
 */
class FlutterwavePaymentHandlerTest extends TestCase
{
    private OrderTransactionService&MockObject $orderTransactionService;
    private PaymentProcessor&MockObject $paymentProcessor;
    private FinalizeProcessor&MockObject $finalizeProcessor;
    private RefundProcessor&MockObject $refundProcessor;
    private FlutterwavePaymentHandler $handler;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->paymentProcessor = $this->createMock(PaymentProcessor::class);
        $this->finalizeProcessor = $this->createMock(FinalizeProcessor::class);
        $this->refundProcessor = $this->createMock(RefundProcessor::class);

        $this->handler = new FlutterwavePaymentHandler(
            $this->orderTransactionService,
            $this->paymentProcessor,
            $this->finalizeProcessor,
            $this->refundProcessor
        );
    }

    public function testPayRedirectsToTheLinkFromThePaymentProcessor(): void
    {
        $transaction = new PaymentTransactionStruct('order-transaction-id', 'http://return.url');
        $context = Context::createDefaultContext();

        $this->paymentProcessor->expects(static::once())
            ->method('process')
            ->with($transaction, $context)
            ->willReturn(new FlutterwaveInitializationResponse('https://flutterwave.com/pay/test-link'));

        $response = $this->handler->pay(new Request(), $transaction, $context, null);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('https://flutterwave.com/pay/test-link', $response->getTargetUrl());
    }

    public function testFinalizeDelegatesToTheFinalizeProcessor(): void
    {
        $request = new Request();
        $transaction = new PaymentTransactionStruct('order-transaction-id', 'http://return.url');
        $context = Context::createDefaultContext();

        $this->finalizeProcessor->expects(static::once())
            ->method('process')
            ->with($request, $transaction, $context);

        $this->handler->finalize($request, $transaction, $context);
    }

    public function testRefundDelegatesToTheRefundProcessor(): void
    {
        $transaction = new RefundPaymentTransactionStruct('refund-id', 'order-transaction-id');
        $context = Context::createDefaultContext();

        $this->refundProcessor->expects(static::once())
            ->method('process')
            ->with($transaction, $context);

        $this->handler->refund($transaction, $context);
    }

    public function testGetOrderTransactionDelegatesToTheService(): void
    {
        $context = Context::createDefaultContext();
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');

        $this->orderTransactionService->expects(static::once())
            ->method('getOrderTransaction')
            ->with('order-transaction-id', $context)
            ->willReturn($orderTransaction);

        static::assertSame($orderTransaction, $this->handler->getOrderTransaction('order-transaction-id', $context));
    }
}
