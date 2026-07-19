<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Util;

use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlutterwaveCurrencyHelper::class)]
class FlutterwaveCurrencyHelperTest extends TestCase
{
    /**
     * @return array<string, array{float, string, int}>
     */
    public static function minorUnitProvider(): array
    {
        return [
            'NGN two decimals' => [20.00, 'NGN', 2000],
            'NGN sub-unit' => [100.55, 'NGN', 10055],
            'GHS two decimals' => [1.05, 'GHS', 105],
            'KES two decimals' => [10.00, 'KES', 1000],
            'RWF zero decimals' => [5000.0, 'RWF', 5000],
            'UGX zero decimals' => [20.0, 'UGX', 20],
            'XOF zero decimals' => [750.0, 'XOF', 750],
            'KWD three decimals' => [20.00, 'KWD', 20000],
            'unknown currency defaults to two' => [20.00, 'ZZZ', 2000],
            'lowercase code' => [20.00, 'ngn', 2000],
        ];
    }

    #[DataProvider('minorUnitProvider')]
    public function testToMinorUnit(float $amount, string $currency, int $expected): void
    {
        static::assertSame($expected, FlutterwaveCurrencyHelper::toMinorUnit($amount, $currency));
    }

    #[DataProvider('minorUnitProvider')]
    public function testFromMinorUnitRoundTrips(float $amount, string $currency, int $minor): void
    {
        static::assertSame($amount, FlutterwaveCurrencyHelper::fromMinorUnit($minor, $currency));
    }

    /**
     * The reason this helper exists. 0.1 + 0.2 is 0.30000000000000004 in binary
     * floating point, so a direct === against 0.3 rejects a valid payment.
     */
    public function testAmountsMatchSurvivesFloatRepresentationError(): void
    {
        $accumulated = 0.1 + 0.2;

        static::assertNotSame(0.3, $accumulated, 'Precondition: floats do not compare equal.');
        static::assertTrue(FlutterwaveCurrencyHelper::amountsMatch($accumulated, 0.3, 'NGN'));
    }

    public function testAmountsMatchRejectsGenuineMismatch(): void
    {
        static::assertFalse(FlutterwaveCurrencyHelper::amountsMatch(100.00, 100.01, 'NGN'));
    }

    /**
     * A 0.01 tolerance — as the old handler used — is a whole unit of slack in a
     * zero-decimal currency, so a 1 RWF discrepancy would pass unnoticed.
     */
    public function testAmountsMatchIsExactForZeroDecimalCurrency(): void
    {
        static::assertFalse(FlutterwaveCurrencyHelper::amountsMatch(5000.0, 5001.0, 'RWF'));
        static::assertTrue(FlutterwaveCurrencyHelper::amountsMatch(5000.0, 5000.0, 'RWF'));
    }

    /**
     * Guards the regression this refactor exists to prevent: Flutterwave V3 takes
     * MAJOR units, so what goes on the wire for a NGN 100 order must be 100 — not
     * the 10000 that Paystack's minor-unit boundary rule would produce.
     *
     * @see https://developer.flutterwave.com/v3.0/docs/flutterwave-standard-1
     */
    public function testForApiKeepsMajorUnits(): void
    {
        static::assertSame(100.0, FlutterwaveCurrencyHelper::forApi(100.00, 'NGN'));
        static::assertNotSame(
            (float)FlutterwaveCurrencyHelper::toMinorUnit(100.00, 'NGN'),
            FlutterwaveCurrencyHelper::forApi(100.00, 'NGN'),
            'The API amount must never be the minor-unit amount.'
        );
    }

    public function testForApiRoundsToCurrencyPrecision(): void
    {
        static::assertSame(100.56, FlutterwaveCurrencyHelper::forApi(100.5551, 'NGN'));
        static::assertSame(5000.0, FlutterwaveCurrencyHelper::forApi(5000.4, 'RWF'));
        static::assertSame(20.001, FlutterwaveCurrencyHelper::forApi(20.0009, 'KWD'));
    }
}
