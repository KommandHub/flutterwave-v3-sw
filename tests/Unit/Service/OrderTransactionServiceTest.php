<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Service;

use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class OrderTransactionServiceTest extends TestCase
{
    private EntityRepository $orderTransactionRepository;
    private OrderTransactionService $orderTransactionService;
    private Context $context;

    protected function setUp(): void
    {
        $this->orderTransactionRepository = $this->createMock(EntityRepository::class);
        $this->orderTransactionService = new OrderTransactionService($this->orderTransactionRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testUpdate(): void
    {
        $payload = [['id' => 'test-id', 'customFields' => ['flutterwave_id' => 'fw-123']]];
        $this->orderTransactionRepository->expects($this->once())
            ->method('update')
            ->with($payload, $this->context);

        $this->orderTransactionService->update($payload, $this->context);
    }

    public function testGetOrderTransaction(): void
    {
        $transactionId = 'test-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn($orderTransaction);

        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->with($this->callback(function (Criteria $criteria) use ($transactionId) {
                return $criteria->getIds() === [$transactionId];
            }), $this->context)
            ->willReturn($searchResult);

        $result = $this->orderTransactionService->getOrderTransaction($transactionId, $this->context);
        $this->assertSame($orderTransaction, $result);
    }

    public function testGetOrderTransactionThrowsExceptionWhenNotFound(): void
    {
        $transactionId = 'non-existent-id';

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(null);

        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order transaction with id non-existent-id not found');

        $this->orderTransactionService->getOrderTransaction($transactionId, $this->context);
    }
}
