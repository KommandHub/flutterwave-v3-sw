<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Checkout\Payment\Service;

use Kommandhub\FlutterwaveSW\Checkout\Payment\Service\RefundEligibilityResolver;
use Kommandhub\FlutterwaveSW\Checkout\Payment\Struct\RefundContext;
use Kommandhub\FlutterwaveSW\Exception\RefundValidationException;
use Kommandhub\FlutterwaveSW\Setting\Service\Config;
use Kommandhub\FlutterwaveSW\Util\FlutterwaveTransactionReader;
use Kommandhub\FlutterwaveSW\Util\OrderCurrencyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[CoversClass(RefundEligibilityResolver::class)]
#[UsesClass(RefundContext::class)]
#[UsesClass(FlutterwaveTransactionReader::class)]
#[UsesClass(OrderCurrencyResolver::class)]
class RefundEligibilityResolverTest extends TestCase
{
    private Config&MockObject $config;
    private RefundEligibilityResolver $resolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->resolver = new RefundEligibilityResolver($this->config);
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function transaction(
        string $state = OrderTransactionStates::STATE_PAID,
        array $customFields = ['flutterwave_transaction_id' => '12345', 'flutterwave_amount_charged' => 100.0],
        ?string $currency = 'NGN'
    ): OrderTransactionEntity {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('order-transaction-id');
        $transaction->setCustomFields($customFields);
        $transaction->setAmount(new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $stateEntity = new StateMachineStateEntity();
        $stateEntity->setId('state-id');
        $stateEntity->setTechnicalName($state);
        $transaction->setStateMachineState($stateEntity);

        $order = new OrderEntity();

        if ($currency !== null) {
            $currencyEntity = new CurrencyEntity();
            $currencyEntity->setIsoCode($currency);
            $order->setCurrency($currencyEntity);
        }

        $transaction->setOrder($order);

        return $transaction;
    }

    public function testThrowsWhenRefundFeatureDisabled(): void
    {
        $this->config->method('getBool')->with('refundEnabled', 'sales-channel-id')->willReturn(false);

        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('Refund feature is currently disabled');

        $this->resolver->resolve($this->transaction(), 'sales-channel-id');
    }

    public function testThrowsWhenTransactionStateIsNotRefundable(): void
    {
        $this->config->method('getBool')->willReturn(true);

        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('not in a refundable state');

        $this->resolver->resolve($this->transaction(OrderTransactionStates::STATE_OPEN), null);
    }

    public function testThrowsWhenFlutterwaveTransactionIdMissing(): void
    {
        $this->config->method('getBool')->willReturn(true);

        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('no Flutterwave transaction id');

        $this->resolver->resolve(
            $this->transaction(customFields: ['flutterwave_amount_charged' => 100.0]),
            null
        );
    }

    /**
     * Guards against the Shopware order_transaction UUID ever being mistaken
     * for a Flutterwave transaction id and sent to the API as one.
     */
    public function testThrowsWhenTransactionIdIsNotNumeric(): void
    {
        $this->config->method('getBool')->willReturn(true);

        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('no Flutterwave transaction id');

        $this->resolver->resolve(
            $this->transaction(customFields: [
                'flutterwave_transaction_id' => '019f6531ce1c73149ce304caf2fe2ecf',
                'flutterwave_amount_charged' => 100.0,
            ]),
            null
        );
    }

    public function testThrowsWhenCurrencyCannotBeResolved(): void
    {
        $this->config->method('getBool')->willReturn(true);

        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('resolve the order currency');

        $this->resolver->resolve($this->transaction(currency: null), null);
    }

    public function testResolvesContextWithConfiguredMinimum(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('get')->with('minimumRefundAmount', null, 'sales-channel-id')->willReturn(25.0);

        $context = $this->resolver->resolve($this->transaction(), 'sales-channel-id');

        static::assertSame('12345', $context->flutterwaveTransactionId);
        static::assertSame('NGN', $context->currencyIso);
        static::assertSame(100.0, $context->chargedAmountMajor);
        static::assertSame(25.0, $context->minimumAmountMajor);
        static::assertSame('sales-channel-id', $context->salesChannelId);
    }

    public function testMinimumFallsBackToPerCurrencyConstantWhenUnconfigured(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('get')->willReturn(null);

        $context = $this->resolver->resolve($this->transaction(), null);

        // FlutterwaveConstants::MINIMUM_REFUND_AMOUNT['NGN'] = 100.0.
        static::assertSame(100.0, $context->minimumAmountMajor);
    }

    public function testMinimumFallsBackToZeroForCurrencyWithNoKnownFloor(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('get')->willReturn(null);

        $context = $this->resolver->resolve($this->transaction(currency: 'USD'), null);

        static::assertSame(0.0, $context->minimumAmountMajor);
    }
}
