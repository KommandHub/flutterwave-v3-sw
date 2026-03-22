<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Service;

use Kommandhub\Flutterwave\Flutterwave;
use Kommandhub\Flutterwave\Payloads\PaymentPayload;
use Kommandhub\Flutterwave\Resources\Transactions;
use Kommandhub\FlutterwaveV3SW\Service\Config;
use Kommandhub\FlutterwaveV3SW\Service\TransactionFactory;
use Kommandhub\FlutterwaveV3SW\Service\TransactionService;
use PHPUnit\Framework\TestCase;

class TransactionServiceTest extends TestCase
{
    private Config $config;
    private TransactionFactory $factory;
    private TransactionService $transactionService;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->factory = $this->createMock(TransactionFactory::class);
        $this->transactionService = new TransactionService($this->config, $this->factory);
    }

    public function testInitialize(): void
    {
        $salesChannelId = 'sales-channel-id';
        $payload = $this->createMock(PaymentPayload::class);
        $transactions = $this->createMock(Transactions::class);

        $this->config->expects($this->once())
            ->method('getApiSecretKey')
            ->with($salesChannelId)
            ->willReturn('sk_test');

        $this->factory->expects($this->once())
            ->method('create')
            ->willReturn($transactions);

        $transactions->expects($this->once())
            ->method('initialize')
            ->with($payload)
            ->willReturn(['status' => 'success']);

        $result = $this->transactionService->initialize($payload, $salesChannelId);
        $this->assertEquals(['status' => 'success'], $result);
    }

    public function testVerify(): void
    {
        $salesChannelId = 'sales-channel-id';
        $transactions = $this->createMock(Transactions::class);

        $this->config->expects($this->once())
            ->method('getApiSecretKey')
            ->with($salesChannelId)
            ->willReturn('sk_test');

        $this->factory->expects($this->once())
            ->method('create')
            ->willReturn($transactions);

        $transactions->expects($this->once())
            ->method('verify')
            ->with('tx-123')
            ->willReturn(['status' => 'success']);

        $result = $this->transactionService->verify('tx-123', $salesChannelId);
        $this->assertEquals(['status' => 'success'], $result);
    }

    public function testInitializeThrowsExceptionWhenSecretKeyIsMissing(): void
    {
        $salesChannelId = 'sales-channel-id';
        $payload = $this->createMock(PaymentPayload::class);

        $this->config->expects($this->once())
            ->method('getApiSecretKey')
            ->with($salesChannelId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Flutterwave secret key is not configured.');

        $this->transactionService->initialize($payload, $salesChannelId);
    }

    /**
     * This test uses a bit of trickery because we can't easily mock the 'new Flutterwave' call.
     * In a real-world scenario, we might use a factory or dependency injection for the SDK client.
     * For now, we'll focus on the logic we can test.
     */
    public function testVerifyThrowsExceptionWhenSecretKeyIsMissing(): void
    {
        $salesChannelId = 'sales-channel-id';

        $this->config->expects($this->once())
            ->method('getApiSecretKey')
            ->with($salesChannelId)
            ->willReturn('');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Flutterwave secret key is not configured.');

        $this->transactionService->verify('transaction-id', $salesChannelId);
    }
}
