<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Checkout\Payment\Service;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundAggregator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class RefundAggregatorTest extends TestCase
{
    private RefundAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new RefundAggregator();
    }

    public function testAggregateThrowsExceptionIfCurrencyMissing(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        // No order associated

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve the order currency for transaction "transaction-1"');

        $this->aggregator->aggregate($transaction, 'refund-1');
    }

    public function testAggregateFullRefund(): void
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('USD');

        $order = new OrderEntity();
        $order->setCurrency($currency);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setOrder($order);
        $transaction->setAmount(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-1');
        $capture->setAmount(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId('refund-1');
        $refund->setAmount(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_OPEN);
        $refund->setStateMachineState($state);

        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $result = $this->aggregator->aggregate($transaction, 'refund-1');

        static::assertTrue($result->isFullyRefunded);
        static::assertCount(1, $result->captures);
        static::assertTrue($result->captures['capture-1']->isFullyRefunded);
    }

    public function testAggregatePartialRefund(): void
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('USD');

        $order = new OrderEntity();
        $order->setCurrency($currency);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setOrder($order);
        $transaction->setAmount(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-1');
        $capture->setAmount(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId('refund-1');
        $refund->setAmount(new CalculatedPrice(40.0, 40.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_COMPLETED);
        $refund->setStateMachineState($state);

        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([$refund]));
        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        $result = $this->aggregator->aggregate($transaction, 'other-refund');

        static::assertFalse($result->isFullyRefunded);
        static::assertCount(1, $result->captures);
        static::assertFalse($result->captures['capture-1']->isFullyRefunded);
    }

    public function testAggregateMultipleCapturesAndRefunds(): void
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('USD');

        $order = new OrderEntity();
        $order->setCurrency($currency);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setOrder($order);
        $transaction->setAmount(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        // Capture 1: 60.0, fully refunded (20 + 40)
        $capture1 = new OrderTransactionCaptureEntity();
        $capture1->setId('capture-1');
        $capture1->setAmount(new CalculatedPrice(60.0, 60.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $refund1 = new OrderTransactionCaptureRefundEntity();
        $refund1->setId('refund-1');
        $refund1->setAmount(new CalculatedPrice(20.0, 20.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $stateComp = new StateMachineStateEntity();
        $stateComp->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_COMPLETED);
        $refund1->setStateMachineState($stateComp);

        $refund2 = new OrderTransactionCaptureRefundEntity();
        $refund2->setId('refund-2');
        $refund2->setAmount(new CalculatedPrice(40.0, 40.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $stateOpen = new StateMachineStateEntity();
        $stateOpen->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_OPEN);
        $refund2->setStateMachineState($stateOpen);

        $capture1->setRefunds(new OrderTransactionCaptureRefundCollection([$refund1, $refund2]));

        // Capture 2: 40.0, partially refunded (10)
        $capture2 = new OrderTransactionCaptureEntity();
        $capture2->setId('capture-2');
        $capture2->setAmount(new CalculatedPrice(40.0, 40.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $refund3 = new OrderTransactionCaptureRefundEntity();
        $refund3->setId('refund-3');
        $refund3->setAmount(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $refund3->setStateMachineState($stateComp);

        $capture2->setRefunds(new OrderTransactionCaptureRefundCollection([$refund3]));

        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture1, $capture2]));

        // Current refund is refund-2
        $result = $this->aggregator->aggregate($transaction, 'refund-2');

        static::assertFalse($result->isFullyRefunded);
        static::assertTrue($result->captures['capture-1']->isFullyRefunded);
        static::assertFalse($result->captures['capture-2']->isFullyRefunded);
    }
}
