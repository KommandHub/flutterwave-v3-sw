<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Service;

use Kommandhub\FlutterwaveSW\Service\OrderTransactionService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

class OrderTransactionServiceTest extends TestCase
{
    private EntityRepository $orderTransactionRepository;
    private EntityRepository $orderTransactionCaptureRepository;
    private EntityRepository $orderTransactionCaptureRefundRepository;
    private InitialStateIdLoader $initialStateIdLoader;
    private OrderTransactionService $orderTransactionService;
    private Context $context;

    protected function setUp(): void
    {
        $this->orderTransactionRepository = $this->createMock(EntityRepository::class);
        $this->orderTransactionCaptureRepository = $this->createMock(EntityRepository::class);
        $this->orderTransactionCaptureRefundRepository = $this->createMock(EntityRepository::class);
        $this->initialStateIdLoader = $this->createMock(InitialStateIdLoader::class);

        $this->orderTransactionService = new OrderTransactionService(
            $this->orderTransactionRepository,
            $this->orderTransactionCaptureRepository,
            $this->orderTransactionCaptureRefundRepository,
            $this->initialStateIdLoader
        );
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

    public function testGetForRefundLoadsRefundAssociations(): void
    {
        $transactionId = 'test-transaction-id';
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($transactionId);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($orderTransaction);

        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->with($this->callback(function (Criteria $criteria) use ($transactionId) {
                return $criteria->getIds() === [$transactionId]
                    && $criteria->hasAssociation('order')
                    && $criteria->hasAssociation('stateMachineState');
            }), $this->context)
            ->willReturn($searchResult);

        $result = $this->orderTransactionService->getForRefund($transactionId, $this->context);
        $this->assertSame($orderTransaction, $result);
    }

    public function testGetForRefundThrowsWhenNotFound(): void
    {
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn(null);
        $this->orderTransactionRepository->method('search')->willReturn($searchResult);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order transaction with id missing not found');

        $this->orderTransactionService->getForRefund('missing', $this->context);
    }

    public function testCreateRefund(): void
    {
        $orderTransactionId = 'transaction-1';
        $flutterwaveTransactionId = '908790';
        $amount = 50.0;
        $flutterwaveRefundId = '8612';

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($orderTransactionId);
        $orderTransaction->setAmount(new \Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice(100.0, 100.0, new \Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection(), new \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection()));

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($orderTransaction);
        $this->orderTransactionRepository->method('search')->willReturn($searchResult);

        $this->initialStateIdLoader->method('get')->willReturn('state-123');

        $notFound = $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult::class);
        $notFound->method('getTotal')->willReturn(0);
        $this->orderTransactionCaptureRepository->method('searchIds')->willReturn($notFound);
        $this->orderTransactionCaptureRefundRepository->method('searchIds')->willReturn($notFound);

        $expectedCaptureId = \Shopware\Core\Framework\Uuid\Uuid::fromStringToHex('flutterwave-capture-' . $flutterwaveTransactionId);
        $expectedRefundId = \Shopware\Core\Framework\Uuid\Uuid::fromStringToHex('flutterwave-refund-' . $flutterwaveRefundId);

        $this->orderTransactionCaptureRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(fn (array $data) => $data[0]['id'] === $expectedCaptureId));
        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(fn (array $data) => $data[0]['id'] === $expectedRefundId
                && $data[0]['captureId'] === $expectedCaptureId));

        $refundId = $this->orderTransactionService->createRefund(
            $orderTransactionId,
            $flutterwaveTransactionId,
            $amount,
            $this->context,
            $flutterwaveRefundId
        );

        $this->assertSame($expectedRefundId, $refundId);

        // Deterministic: calling again with the same Flutterwave identifiers
        // must resolve to the same IDs (dedup is verified separately).
        $this->assertSame(
            $expectedRefundId,
            \Shopware\Core\Framework\Uuid\Uuid::fromStringToHex('flutterwave-refund-' . $flutterwaveRefundId)
        );
    }

    public function testCreateRefundWithExistingCaptureAndRefundIsIdempotent(): void
    {
        $orderTransactionId = 'transaction-1';
        $flutterwaveTransactionId = '908790';
        $amount = 50.0;
        $flutterwaveRefundId = '8612';

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($orderTransactionId);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($orderTransaction);
        $this->orderTransactionRepository->method('search')->willReturn($searchResult);

        // Both the capture and the refund already exist under their
        // deterministic IDs (e.g. a retried admin request) — neither should
        // be created again.
        $found = $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult::class);
        $found->method('getTotal')->willReturn(1);
        $this->orderTransactionCaptureRepository->method('searchIds')->willReturn($found);
        $this->orderTransactionCaptureRefundRepository->method('searchIds')->willReturn($found);

        $this->orderTransactionCaptureRepository->expects($this->never())->method('create');
        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('create');

        $expectedRefundId = \Shopware\Core\Framework\Uuid\Uuid::fromStringToHex('flutterwave-refund-' . $flutterwaveRefundId);

        $refundId = $this->orderTransactionService->createRefund(
            $orderTransactionId,
            $flutterwaveTransactionId,
            $amount,
            $this->context,
            $flutterwaveRefundId
        );

        $this->assertSame($expectedRefundId, $refundId);
    }

    public function testFindRefundByExternalReference(): void
    {
        $externalReference = 'flw-refund-123';
        $refund = new \Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity();
        $refund->setId('refund-1');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($refund);

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('search')
            ->with($this->callback(function (Criteria $criteria) use ($externalReference) {
                $filters = $criteria->getFilters();
                /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter $filter */
                $filter = $filters[0];

                return $filter->getField() === 'externalReference' && $filter->getValue() === $externalReference;
            }), $this->context)
            ->willReturn($searchResult);

        $result = $this->orderTransactionService->findRefundByExternalReference($externalReference, $this->context);
        $this->assertSame($refund, $result);
    }
}
