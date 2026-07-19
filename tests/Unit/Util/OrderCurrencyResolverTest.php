<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Util;

use Kommandhub\FlutterwaveV3SW\Util\OrderCurrencyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(OrderCurrencyResolver::class)]
class OrderCurrencyResolverTest extends TestCase
{
    private function createTransaction(?string $isoCode, bool $withOrder = true): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('order-transaction-id');

        if (!$withOrder) {
            return $transaction;
        }

        $order = new OrderEntity();

        if ($isoCode !== null) {
            $currency = new CurrencyEntity();
            $currency->setIsoCode($isoCode);
            $order->setCurrency($currency);
        }

        $transaction->setOrder($order);

        return $transaction;
    }

    public function testResolveReturnsIsoCode(): void
    {
        static::assertSame('NGN', OrderCurrencyResolver::resolve($this->createTransaction('NGN')));
    }

    public function testResolveReturnsNullWhenCurrencyAssociationMissing(): void
    {
        static::assertNull(OrderCurrencyResolver::resolve($this->createTransaction(null)));
    }

    public function testResolveReturnsNullWhenOrderAssociationMissing(): void
    {
        static::assertNull(OrderCurrencyResolver::resolve($this->createTransaction(null, false)));
    }

    public function testResolveOrFailReturnsIsoCode(): void
    {
        static::assertSame('GHS', OrderCurrencyResolver::resolveOrFail($this->createTransaction('GHS')));
    }

    /**
     * The whole point of the resolver: it must fail closed rather than assume a
     * currency, because an assumed currency silently rescales every comparison.
     */
    public function testResolveOrFailThrowsRatherThanAssumingACurrency(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve the order currency for transaction "order-transaction-id"');

        OrderCurrencyResolver::resolveOrFail($this->createTransaction(null));
    }
}
