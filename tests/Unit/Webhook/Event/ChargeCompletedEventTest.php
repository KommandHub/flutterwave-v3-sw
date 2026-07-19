<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Webhook\Event;

use Kommandhub\FlutterwaveSW\Webhook\Event\ChargeCompletedEvent;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class ChargeCompletedEventTest extends TestCase
{
    public function testGetWebhookName(): void
    {
        static::assertSame('charge.completed', ChargeCompletedEvent::getWebhookName());
    }

    public function testGetDataAndContext(): void
    {
        $data = ['id' => '12345', 'tx_ref' => 'REF-123'];
        $context = Context::createDefaultContext();
        $event = new ChargeCompletedEvent($data, $context);

        static::assertSame($data, $event->getData());
        static::assertSame($context, $event->getContext());
    }

    public function testGetFlutterwaveTransactionId(): void
    {
        $event = new ChargeCompletedEvent(['id' => '12345'], Context::createDefaultContext());
        static::assertSame('12345', $event->getFlutterwaveTransactionId());

        $event = new ChargeCompletedEvent([], Context::createDefaultContext());
        static::assertNull($event->getFlutterwaveTransactionId());

        $event = new ChargeCompletedEvent(['id' => ''], Context::createDefaultContext());
        static::assertNull($event->getFlutterwaveTransactionId());
    }

    public function testGetReference(): void
    {
        $event = new ChargeCompletedEvent(['tx_ref' => 'REF-123'], Context::createDefaultContext());
        static::assertSame('REF-123', $event->getReference());

        $event = new ChargeCompletedEvent([], Context::createDefaultContext());
        static::assertNull($event->getReference());
    }

    public function testScalarHelper(): void
    {
        // Testing WebhookEvent::scalar through ChargeCompletedEvent
        $data = [
            'key1' => 'value1',
            'key2' => 123,
            'key3' => '',
            'key4' => null,
        ];
        $event = new class($data, Context::createDefaultContext()) extends \Kommandhub\FlutterwaveSW\Webhook\Event\WebhookEvent {
            public static function getWebhookName(): string
            {
                return 'test';
            }
            public function testScalar(string ...$keys): ?string
            {
                return $this->scalar(...$keys);
            }
        };

        static::assertSame('value1', $event->testScalar('key1'));
        static::assertSame('123', $event->testScalar('key2'));
        static::assertNull($event->testScalar('key3'));
        static::assertNull($event->testScalar('key4'));
        static::assertSame('value1', $event->testScalar('absent', 'key1'));
        static::assertSame('123', $event->testScalar('key3', 'key2'));
    }
}
