<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Webhook\Service;

use Kommandhub\FlutterwaveSW\Webhook\Event\ChargeCompletedEvent;
use Kommandhub\FlutterwaveSW\Webhook\Event\RefundCompletedEvent;
use Kommandhub\FlutterwaveSW\Webhook\Service\WebhookEventFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(WebhookEventFactory::class)]
#[UsesClass(ChargeCompletedEvent::class)]
#[UsesClass(RefundCompletedEvent::class)]
class WebhookEventFactoryTest extends TestCase
{
    private WebhookEventFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new WebhookEventFactory();
    }

    public function testCreatesChargeCompleted(): void
    {
        $event = $this->factory->create('charge.completed', ['id' => 1], Context::createDefaultContext());

        static::assertInstanceOf(ChargeCompletedEvent::class, $event);
        static::assertSame(['id' => 1], $event->getData());
    }

    public function testCreatesRefundCompleted(): void
    {
        $event = $this->factory->create('refund.completed', ['id' => 1], Context::createDefaultContext());

        static::assertInstanceOf(RefundCompletedEvent::class, $event);
    }

    public function testEventNameIsCaseInsensitive(): void
    {
        static::assertInstanceOf(
            ChargeCompletedEvent::class,
            $this->factory->create('CHARGE.COMPLETED', [], Context::createDefaultContext())
        );
    }

    /**
     * Events this plugin has no business acting on: it makes no payouts, sells
     * no subscriptions and pays no bills.
     *
     * @return array<string, array{string}>
     */
    public static function unsupportedEventProvider(): array
    {
        return [
            'transfer' => ['transfer.completed'],
            'subscription' => ['subscription.cancelled'],
            'bvn' => ['bvn.completed'],
            'bill payment' => ['singlebillpayment.status'],
            'unknown' => ['something.new'],
        ];
    }

    #[DataProvider('unsupportedEventProvider')]
    public function testUnsupportedEventsReturnNull(string $eventName): void
    {
        static::assertNull($this->factory->create($eventName, [], Context::createDefaultContext()));
        static::assertFalse($this->factory->supports($eventName));
    }

    public function testSupportsHandledEvents(): void
    {
        static::assertTrue($this->factory->supports('charge.completed'));
        static::assertTrue($this->factory->supports('refund.completed'));
    }
}
