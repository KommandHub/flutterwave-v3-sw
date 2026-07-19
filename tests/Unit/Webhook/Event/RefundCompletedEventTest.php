<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Webhook\Event;

use Kommandhub\FlutterwaveV3SW\Webhook\Event\RefundCompletedEvent;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class RefundCompletedEventTest extends TestCase
{
    public function testGetWebhookName(): void
    {
        static::assertSame('refund.completed', RefundCompletedEvent::getWebhookName());
    }

    public function testGetRefundId(): void
    {
        $event = new RefundCompletedEvent(['id' => '8612'], Context::createDefaultContext());
        static::assertSame('8612', $event->getRefundId());
    }

    public function testGetFlutterwaveTransactionId(): void
    {
        $event = new RefundCompletedEvent(['TransactionId' => '908790'], Context::createDefaultContext());
        static::assertSame('908790', $event->getFlutterwaveTransactionId());

        $event = new RefundCompletedEvent(['tx_id' => '908791'], Context::createDefaultContext());
        static::assertSame('908791', $event->getFlutterwaveTransactionId());

        $event = new RefundCompletedEvent(['transaction_id' => '908792'], Context::createDefaultContext());
        static::assertSame('908792', $event->getFlutterwaveTransactionId());
    }

    public function testGetStatus(): void
    {
        $event = new RefundCompletedEvent(['status' => 'COMPLETED'], Context::createDefaultContext());
        static::assertSame('completed', $event->getStatus());

        $event = new RefundCompletedEvent(['status' => 'Failed'], Context::createDefaultContext());
        static::assertSame('failed', $event->getStatus());

        $event = new RefundCompletedEvent([], Context::createDefaultContext());
        static::assertNull($event->getStatus());
    }
}
