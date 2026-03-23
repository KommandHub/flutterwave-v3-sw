<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Integration\Checkout\Payment;

use Kommandhub\Flutterwave\Payloads\PaymentPayload;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\FlutterwaveTransactionHandler;
use Kommandhub\FlutterwaveV3SW\Service\Config;
use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveV3SW\Service\PayloadBuilder;
use Kommandhub\FlutterwaveV3SW\Service\TransactionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class FlutterwaveTransactionHandlerTest extends TestCase
{
    private OrderTransactionService $orderTransactionService;
    private TransactionService $transactionService;
    private PayloadBuilder $payloadBuilder;
    private OrderTransactionStateHandler $transactionStateHandler;
    private Config $config;
    private LoggerInterface $logger;
    private FlutterwaveTransactionHandler $handler;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->payloadBuilder = $this->createMock(PayloadBuilder::class);
        $this->transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new FlutterwaveTransactionHandler(
            $this->orderTransactionService,
            $this->transactionService,
            $this->payloadBuilder,
            $this->transactionStateHandler,
            $this->config,
            $this->logger
        );
    }

    public function testPayRedirectsToFlutterwave(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request();

        $this->orderTransactionService->expects($this->once())
            ->method('getOrderTransaction')
            ->with($transactionId, $context)
            ->willReturn($orderTransaction);

        $payload = $this->createMock(PaymentPayload::class);
        $this->payloadBuilder->expects($this->once())
            ->method('build')
            ->with($orderTransaction, $paymentTransactionStruct)
            ->willReturn($payload);

        $this->transactionService->expects($this->once())
            ->method('initialize')
            ->with($payload, 'sales-channel-id')
            ->willReturn([
                'status' => 'success',
                'data' => ['link' => 'https://flutterwave.com/pay/test-link']
            ]);

        $response = $this->handler->pay($request, $paymentTransactionStruct, $context, null);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('https://flutterwave.com/pay/test-link', $response->getTargetUrl());
    }

    public function testFinalizeHandlesSuccessfulPayment(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);
        
        // Mocking amount and currency for verification
        $orderTransaction->setAmount(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(100.0, 100.0, new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(), new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()));
        $currency = new \Shopware\Core\System\Currency\CurrencyEntity();
        $currency->setIsoCode('NGN');
        $order->setCurrency($currency);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['status' => 'successful', 'transaction_id' => '12345']);

        $this->orderTransactionService->expects($this->once())
            ->method('getOrderTransaction')
            ->with($transactionId, $context)
            ->willReturn($orderTransaction);

        $this->transactionService->expects($this->once())
            ->method('verify')
            ->with('12345', 'sales-channel-id')
            ->willReturn([
                'status' => 'success',
                'data' => [
                    'id' => 12345,
                    'status' => 'successful',
                    'amount' => 100.0,
                    'currency' => 'NGN',
                    'tx_ref' => 'tx-ref-123',
                    'payment_type' => 'card',
                    'app_fee' => 1.5,
                    'amount_settled' => 98.5,
                ]
            ]);

        $this->orderTransactionService->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($payload) use ($transactionId) {
                return $payload[0]['id'] === $transactionId && 
                       isset($payload[0]['customFields']) && 
                       $payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_TRANSACTION_ID] === '12345' &&
                       $payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_REFERENCE] === 'tx-ref-123' &&
                       $payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_PAYMENT_TYPE] === 'card' &&
                       $payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_TRANSACTION_FEE] === 1.5 &&
                       $payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_AMOUNT_CHARGED] === 100.0 &&
                       $payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_AMOUNT_SETTLED] === 98.5 &&
                       $payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_CURRENCY] === 'NGN' &&
                       isset($payload[0]['customFields'][FlutterwaveTransactionHandler::FIELD_VERIFIED_AT]);
            }), $context);

        $this->transactionStateHandler->expects($this->once())
            ->method('paid')
            ->with($transactionId, $context);

        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testPayHandlesExceptions(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request();

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);
        $this->payloadBuilder->method('build')->willReturn($this->createMock(PaymentPayload::class));
        $this->transactionService->method('initialize')->willThrowException(new \Exception('API Error'));

        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->logger->expects($this->once())->method('error');

        $this->expectException(\Exception::class);
        $this->handler->pay($request, $paymentTransactionStruct, $context, null);
    }

    public function testFinalizeHandlesCancelledPayment(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['status' => 'cancelled']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $this->transactionStateHandler->expects($this->once())
            ->method('cancel')
            ->with($transactionId, $context);

        $this->expectException(\Shopware\Core\Checkout\Payment\PaymentException::class);
        try {
            $this->handler->finalize($request, $paymentTransactionStruct, $context);
        } catch (\Shopware\Core\Checkout\Payment\PaymentException $e) {
            $this->assertStringContainsString('Customer canceled the payment on Flutterwave.', $e->getMessage());
            throw $e;
        }
    }

    public function testFinalizeHandlesFailedStatusInVerification(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);
        $orderTransaction->setAmount(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(100.0, 100.0, new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(), new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()));

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['status' => 'successful', 'transaction_id' => '12345']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $this->transactionService->expects($this->once())
            ->method('verify')
            ->willReturn([
                'status' => 'success',
                'data' => ['status' => 'failed', 'amount' => 100.0, 'currency' => null]
            ]);

        $this->transactionStateHandler->expects($this->once())
            ->method('fail')
            ->with($transactionId, $context);

        $this->expectException(\Shopware\Core\Checkout\Payment\PaymentException::class);
        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testFinalizeHandlesMismatchedAmount(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);
        $orderTransaction->setAmount(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(100.0, 100.0, new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(), new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()));

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['status' => 'successful', 'transaction_id' => '12345']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $this->transactionService->method('verify')->willReturn([
            'status' => 'success',
            'data' => [
                'status' => 'successful',
                'amount' => 50.0,
                'currency' => 'NGN'
            ]
        ]);

        $this->expectException(\Shopware\Core\Checkout\Payment\PaymentException::class);
        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testFinalizeHandlesMissingTransactionId(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(); // No transaction_id

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);

        $this->expectException(\Shopware\Core\Checkout\Payment\PaymentException::class);
        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testFinalizeHandlesVerificationFailureResponse(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['transaction_id' => '12345']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);
        $this->transactionService->method('verify')->willReturn(['status' => 'error', 'message' => 'API Error']);

        $this->expectException(\Shopware\Core\Checkout\Payment\PaymentException::class);
        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testFinalizeHandlesReopenStatus(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);
        $orderTransaction->setAmount(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(100.0, 100.0, new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(), new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()));

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['transaction_id' => '12345']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);
        $this->transactionService->method('verify')->willReturn([
            'status' => 'success',
            'data' => [
                'status' => 'pending',
                'amount' => 100.0,
                'currency' => null
            ]
        ]);

        $this->orderTransactionService->expects($this->once())->method('update');
        $this->transactionStateHandler->expects($this->once())->method('reopen');

        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testPayLogsIfDebugEnabled(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request();

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);
        $this->payloadBuilder->method('build')->willReturn($this->createMock(PaymentPayload::class));
        $this->transactionService->method('initialize')->willReturn([
            'status' => 'success',
            'data' => ['link' => 'http://link']
        ]);

        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('info');

        $this->handler->pay($request, $paymentTransactionStruct, $context, null);
    }

    public function testFinalizeLogsIfDebugEnabled(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);
        $orderTransaction->setAmount(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(100.0, 100.0, new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(), new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()));

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['transaction_id' => '12345', 'status' => 'successful']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);
        $this->transactionService->method('verify')->willReturn([
            'status' => 'success',
            'data' => [
                'status' => 'successful',
                'amount' => 100.0,
                'currency' => null
            ]
        ]);

        $this->orderTransactionService->expects($this->once())->method('update');
        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('info');

        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testFinalizeHandlesFailedStatusWithDebug(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);
        $orderTransaction->setAmount(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(100.0, 100.0, new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(), new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()));

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['transaction_id' => '12345']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);
        $this->transactionService->method('verify')->willReturn([
            'status' => 'success',
            'data' => ['status' => 'failed', 'amount' => 100.0, 'currency' => null]
        ]);

        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('info');

        $this->expectException(\Shopware\Core\Checkout\Payment\PaymentException::class);
        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }

    public function testFinalizeHandlesVerificationErrorWithDebug(): void
    {
        $transactionId = 'order-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);
        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct($transactionId, 'http://return.url');
        $context = Context::createDefaultContext();
        $request = new Request(['transaction_id' => '12345']);

        $this->orderTransactionService->method('getOrderTransaction')->willReturn($orderTransaction);
        $this->transactionService->method('verify')->willThrowException(new \Exception('Verification Error'));

        $this->config->method('isDebugEnabled')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('error');

        $this->expectException(\Shopware\Core\Checkout\Payment\PaymentException::class);
        $this->handler->finalize($request, $paymentTransactionStruct, $context);
    }
}
