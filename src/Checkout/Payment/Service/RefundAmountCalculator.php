<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Struct\RefundContext;
use Kommandhub\FlutterwaveV3SW\Exception\RefundValidationException;
use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveCurrencyHelper;

/**
 * Validates a requested refund amount against a transaction's refundable
 * balance and resolves the exact amount to send to Flutterwave.
 *
 * A pure calculation with no I/O and no Shopware/Flutterwave dependencies
 * beyond {@see FlutterwaveCurrencyHelper} — everything it needs (what has
 * already been refunded, what is still refundable, the configured floor) is
 * passed in, which is what makes it trivial to unit test against exact
 * money values without mocking an HTTP client or a repository.
 *
 * All comparisons happen in minor units via {@see FlutterwaveCurrencyHelper},
 * never on the raw major-unit floats: `0.1 + 0.2 !== 0.3` in binary floating
 * point, so comparing floats directly would either reject valid refunds or,
 * with a tolerance, silently accept a discrepancy. Scaling to whole minor
 * units makes every comparison here exact, and respects each currency's own
 * decimal count (a 0.01 tolerance would be a whole unit of slack for a
 * zero-decimal currency like RWF).
 *
 * @final
 */
final readonly class RefundAmountCalculator
{
    /**
     * @param RefundContext $context Resolved by {@see RefundEligibilityResolver}:
     *                               supplies the charged amount, the configured
     *                               minimum, and the currency all comparisons
     *                               are scaled by.
     * @param int $alreadyRefundedMinor Sum of this transaction's refunds that
     *                                  already consume balance, in minor
     *                                  units, from
     *                                  {@see FlutterwaveRefundLedgerInterface::alreadyRefundedMinor()}.
     * @param float|null $requestedAmountMajor The merchant-entered amount, in
     *                                         major units, or null for "refund
     *                                         everything still remaining".
     *
     * @return float The amount to send to Flutterwave, in major units.
     *
     * @throws RefundValidationException When the resolved amount is below the
     *                                   configured minimum or above the
     *                                   remaining refundable balance.
     */
    public function calculate(
        RefundContext $context,
        int $alreadyRefundedMinor,
        ?float $requestedAmountMajor
    ): float {
        $chargedMinor = FlutterwaveCurrencyHelper::toMinorUnit($context->chargedAmountMajor, $context->currencyIso);
        $remainingMinor = max(0, $chargedMinor - $alreadyRefundedMinor);

        // No amount means a full refund of whatever is still remaining.
        $requestedMinor = $requestedAmountMajor !== null
            ? FlutterwaveCurrencyHelper::toMinorUnit($requestedAmountMajor, $context->currencyIso)
            : $remainingMinor;

        $minMinor = FlutterwaveCurrencyHelper::toMinorUnit($context->minimumAmountMajor, $context->currencyIso);

        if ($requestedMinor < $minMinor) {
            throw new RefundValidationException(sprintf(
                'Refund amount must be at least %s %s',
                FlutterwaveCurrencyHelper::fromMinorUnit($minMinor, $context->currencyIso),
                $context->currencyIso
            ));
        }

        if ($requestedMinor > $remainingMinor) {
            throw new RefundValidationException(sprintf(
                'Refund amount exceeds the refundable balance of %s %s',
                FlutterwaveCurrencyHelper::fromMinorUnit($remainingMinor, $context->currencyIso),
                $context->currencyIso
            ));
        }

        return FlutterwaveCurrencyHelper::fromMinorUnit($requestedMinor, $context->currencyIso);
    }
}
