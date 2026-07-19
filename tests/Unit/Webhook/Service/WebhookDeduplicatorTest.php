<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Webhook\Service;

use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveConstants;
use Kommandhub\FlutterwaveV3SW\Webhook\Service\WebhookDeduplicator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(WebhookDeduplicator::class)]
class WebhookDeduplicatorTest extends TestCase
{
    private OrderTransactionService&MockObject $orderTransactionService;
    private WebhookDeduplicator $deduplicator;
    private Context $context;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->deduplicator = new WebhookDeduplicator($this->orderTransactionService);
        $this->context = Context::createDefaultContext();
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function transaction(array $customFields = []): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('order-transaction-id');
        $transaction->setCustomFields($customFields);

        return $transaction;
    }

    public function testBuildKeyNamespacesByEvent(): void
    {
        static::assertSame('charge.completed:12345', $this->deduplicator->buildKey('charge.completed', '12345'));
        // The same identifier under a different event must not collide.
        static::assertNotSame(
            $this->deduplicator->buildKey('charge.completed', '1'),
            $this->deduplicator->buildKey('refund.completed', '1')
        );
    }

    public function testUnseenEventIsNotProcessed(): void
    {
        static::assertFalse($this->deduplicator->isProcessed($this->transaction(), 'charge.completed:1'));
    }

    public function testRecordedEventIsProcessed(): void
    {
        $transaction = $this->transaction([
            FlutterwaveConstants::FIELD_PROCESSED_EVENTS => ['charge.completed:1'],
        ]);

        static::assertTrue($this->deduplicator->isProcessed($transaction, 'charge.completed:1'));
        static::assertFalse($this->deduplicator->isProcessed($transaction, 'charge.completed:2'));
    }

    public function testMarkProcessedPersistsAndPreservesExistingCustomFields(): void
    {
        $transaction = $this->transaction(['unrelated' => 'keep']);

        $this->orderTransactionService->expects(static::once())
            ->method('update')
            ->with(static::callback(static function (array $payload): bool {
                $fields = $payload[0]['customFields'];

                return $payload[0]['id'] === 'order-transaction-id'
                    && $fields['unrelated'] === 'keep'
                    && $fields[FlutterwaveConstants::FIELD_PROCESSED_EVENTS] === ['charge.completed:1'];
            }), $this->context);

        $this->deduplicator->markProcessed($transaction, 'charge.completed:1', $this->context);

        // The in-memory entity is updated too, so a second check in the same
        // request sees the mark without a re-read.
        static::assertTrue($this->deduplicator->isProcessed($transaction, 'charge.completed:1'));
    }

    public function testMarkProcessedIsIdempotent(): void
    {
        $transaction = $this->transaction([
            FlutterwaveConstants::FIELD_PROCESSED_EVENTS => ['charge.completed:1'],
        ]);

        $this->orderTransactionService->expects(static::never())->method('update');

        $this->deduplicator->markProcessed($transaction, 'charge.completed:1', $this->context);
    }

    public function testCorruptProcessedEventsFieldIsTreatedAsEmpty(): void
    {
        $transaction = $this->transaction([FlutterwaveConstants::FIELD_PROCESSED_EVENTS => 'not-an-array']);

        static::assertFalse($this->deduplicator->isProcessed($transaction, 'charge.completed:1'));
    }
}
