<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Util;

/**
 * Compares money amounts exactly, by scaling them to whole minor units.
 *
 * IMPORTANT — this is NOT a wire-format converter, and differs from the
 * Paystack plugin on exactly this point:
 *
 *   Paystack's API takes MINOR units. `PaystackCurrencyHelper::toMinorUnit()`
 *   is applied at the API boundary; ₦20.00 is sent as 2000.
 *
 *   Flutterwave V3's API takes MAJOR units. ₦100.00 is sent as `"amount": "100"`.
 *   Scaling an amount before sending it would overcharge by 10^decimals — 100x
 *   for NGN. Never call this on a value destined for a request payload.
 *
 * @see https://developer.flutterwave.com/v3.0/docs/flutterwave-standard-1
 *
 * What it IS for: comparing an amount Flutterwave reports against the amount
 * Shopware expects. Both sides are floats, and `0.1 + 0.2 !== 0.3` in binary
 * floating point, so comparing them directly either rejects good payments or —
 * with a tolerance like `abs($a - $b) > 0.01` — silently accepts a discrepancy.
 * Scaling both sides to integers with the currency's own decimal count makes the
 * comparison exact and respects zero-decimal currencies (RWF, UGX, XOF), where a
 * 0.01 tolerance would be a whole unit of slack.
 */
final class FlutterwaveCurrencyHelper
{
    /**
     * Currency => number of decimal places, for currencies Flutterwave settles in.
     *
     * @see https://en.wikipedia.org/wiki/ISO_4217
     */
    private const DECIMALS = [
        // Zero-decimal currencies. A 0.01 float tolerance is meaningless here.
        'XOF' => 0,
        'XAF' => 0,
        'RWF' => 0,
        'UGX' => 0,
        'JPY' => 0,
        'GNF' => 0,
        'KMF' => 0,

        // Two-decimal currencies.
        'NGN' => 2,
        'GHS' => 2,
        'KES' => 2,
        'ZAR' => 2,
        'TZS' => 2,
        'ZMW' => 2,
        'MWK' => 2,
        'SLL' => 2,
        'EGP' => 2,
        'MAD' => 2,
        'USD' => 2,
        'EUR' => 2,
        'GBP' => 2,
        'CAD' => 2,

        // Three-decimal currencies.
        'BHD' => 3,
        'IQD' => 3,
        'JOD' => 3,
        'KWD' => 3,
        'LYD' => 3,
        'OMR' => 3,
        'TND' => 3,
    ];

    /**
     * Scales a major-unit amount to whole minor units, for comparison only.
     */
    public static function toMinorUnit(float $amount, string $currencyCode): int
    {
        return (int)round(
            $amount * (10 ** self::decimalsFor($currencyCode)),
            0,
            PHP_ROUND_HALF_UP
        );
    }

    /**
     * Scales minor units back to a major-unit amount.
     */
    public static function fromMinorUnit(int $amount, string $currencyCode): float
    {
        return $amount / (10 ** self::decimalsFor($currencyCode));
    }

    /**
     * Exact equality between two major-unit amounts in the same currency.
     */
    public static function amountsMatch(float $a, float $b, string $currencyCode): bool
    {
        return self::toMinorUnit($a, $currencyCode) === self::toMinorUnit($b, $currencyCode);
    }

    /**
     * Rounds a major-unit amount to the currency's own precision, for sending to
     * Flutterwave. This is the only conversion that may touch a request payload.
     */
    public static function forApi(float $amount, string $currencyCode): float
    {
        return round($amount, self::decimalsFor($currencyCode));
    }

    /**
     * Unknown currencies fall back to 2 decimals — the ISO 4217 majority. This is
     * safe for comparison (both sides scale identically, so equality is
     * preserved) but would not be safe for a wire conversion, which is another
     * reason this class does not do one.
     */
    private static function decimalsFor(string $currencyCode): int
    {
        return self::DECIMALS[strtoupper($currencyCode)] ?? 2;
    }
}
