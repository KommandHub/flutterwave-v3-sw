<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Checkout\Payment\Struct;

/**
 * Everything the refund flow needs to know about a transaction before an
 * amount can be validated and sent to Flutterwave, resolved once by
 * {@see \Kommandhub\FlutterwaveSW\Checkout\Payment\Service\RefundEligibilityResolver}
 * and then threaded through the rest of the flow.
 *
 * Bundling these as one value object (rather than passing four loose
 * scalars) keeps the refund/amount-calculation call sites stable as the set
 * of "things we know about this transaction" grows, and makes it impossible
 * to accidentally swap two same-typed parameters (e.g. two floats) at a call
 * site.
 *
 * @final
 */
final readonly class RefundContext
{
    /**
     * @param string $flutterwaveTransactionId Flutterwave's numeric transaction id.
     * @param string $currencyIso ISO 4217 code of the order's currency.
     * @param float $chargedAmountMajor Amount Flutterwave charged, in major units.
     * @param float $minimumAmountMajor The smallest refund allowed for this
     *                                  currency/sales channel, in major units.
     * @param string|null $salesChannelId Sales channel the order belongs to,
     *                                    used to resolve per-channel config
     *                                    and API credentials.
     */
    public function __construct(
        public string $flutterwaveTransactionId,
        public string $currencyIso,
        public float $chargedAmountMajor,
        public float $minimumAmountMajor,
        public ?string $salesChannelId,
    ) {
    }
}
