<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Checkout\Payment\Service;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundAmountCalculator;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Struct\RefundContext;
use Kommandhub\FlutterwaveV3SW\Exception\RefundValidationException;
use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundAmountCalculator::class)]
#[UsesClass(RefundContext::class)]
#[UsesClass(FlutterwaveCurrencyHelper::class)]
class RefundAmountCalculatorTest extends TestCase
{
    private RefundAmountCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new RefundAmountCalculator();
    }

    private function context(
        float $chargedAmountMajor = 100.0,
        float $minimumAmountMajor = 1.0,
        string $currencyIso = 'NGN'
    ): RefundContext {
        return new RefundContext('12345', $currencyIso, $chargedAmountMajor, $minimumAmountMajor, 'sales-channel-id');
    }

    public function testNullAmountRefundsWhatRemainsAfterAlreadyRefunded(): void
    {
        // Charged 100, 30 already refunded (in minor units) => 70 remaining.
        $result = $this->calculator->calculate($this->context(), 3000, null);

        static::assertSame(70.0, $result);
    }

    public function testRequestedAmountWithinBalanceIsReturnedUnchanged(): void
    {
        $result = $this->calculator->calculate($this->context(), 0, 25.5);

        static::assertSame(25.5, $result);
    }

    public function testThrowsWhenBelowConfiguredMinimum(): void
    {
        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('Refund amount must be at least 100 NGN');

        $this->calculator->calculate($this->context(minimumAmountMajor: 100.0), 0, 10.0);
    }

    public function testThrowsWhenAboveRemainingBalance(): void
    {
        // Charged 100, 40 already refunded => 60 remaining. Requesting 70 fails.
        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('Refund amount exceeds the refundable balance of 60 NGN');

        $this->calculator->calculate($this->context(), 4000, 70.0);
    }

    public function testAmountExactlyAtRemainingBalanceSucceeds(): void
    {
        $result = $this->calculator->calculate($this->context(), 0, 100.0);

        static::assertSame(100.0, $result);
    }

    public function testAmountExactlyAtMinimumSucceeds(): void
    {
        $result = $this->calculator->calculate($this->context(minimumAmountMajor: 50.0), 0, 50.0);

        static::assertSame(50.0, $result);
    }

    /**
     * A zero-decimal currency (RWF has no minor-unit fraction) must not gain
     * artificial slack from the minor-unit scaling used for comparisons.
     */
    public function testRespectsZeroDecimalCurrencyPrecision(): void
    {
        $result = $this->calculator->calculate(
            $this->context(chargedAmountMajor: 1000.0, minimumAmountMajor: 1.0, currencyIso: 'RWF'),
            0,
            1000.0
        );

        static::assertSame(1000.0, $result);
    }

    public function testAlreadyFullyRefundedLeavesNothingForANullAmount(): void
    {
        $this->expectException(RefundValidationException::class);
        $this->expectExceptionMessage('Refund amount must be at least 1 NGN');

        // Charged 100, 100 already refunded (in minor units) => nothing remains.
        $this->calculator->calculate($this->context(), 10000, null);
    }
}
