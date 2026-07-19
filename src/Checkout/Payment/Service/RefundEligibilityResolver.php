<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Struct\RefundContext;
use Kommandhub\FlutterwaveV3SW\Exception\RefundValidationException;
use Kommandhub\FlutterwaveV3SW\Setting\Service\Config;
use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveConstants;
use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveTransactionReader;
use Kommandhub\FlutterwaveV3SW\Util\OrderCurrencyResolver;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

/**
 * Decides whether an order transaction may be refunded at all, and gathers
 * the static (config- and transaction-derived) facts the rest of the refund
 * flow needs.
 *
 * This is the single place the admin refund endpoint's *eligibility* rules
 * live — as opposed to {@see RefundAmountCalculator}, which validates a
 * specific *amount* once eligibility is already established, and
 * {@see FlutterwaveRefundLedgerInterface}, which supplies the one piece of
 * eligibility this class deliberately does NOT resolve: how much has already
 * been refunded (that requires an API call, so it stays out of this
 * synchronous, side-effect-free resolver).
 *
 * Every failure is a {@see RefundValidationException} carrying a
 * merchant-facing message, so the controller can catch one exception type
 * and turn it directly into an HTTP error response without needing to know
 * which specific rule failed.
 *
 * @final
 */
final readonly class RefundEligibilityResolver
{
    /**
     * Order transaction states a refund may be raised from. Any other state
     * (open, failed, cancelled, fully refunded, ...) is rejected — Flutterwave
     * itself would likely reject those too, but failing here avoids a wasted
     * API round trip and gives a clearer message than a gateway error would.
     */
    private const REFUNDABLE_STATES = [
        OrderTransactionStates::STATE_PAID,
        OrderTransactionStates::STATE_PARTIALLY_PAID,
        OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
    ];

    public function __construct(private Config $config)
    {
    }

    /**
     * Resolves the refund context for a transaction, or throws.
     *
     * Checks run in a fixed order — feature flag, then transaction state,
     * then the Flutterwave transaction id, then the order currency — so that
     * whichever fails first is reported; a transaction that fails several
     * checks at once still gets one clear reason rather than the last check
     * evaluated "winning" arbitrarily.
     *
     * @throws RefundValidationException When the refund feature is disabled
     *                                   for this sales channel, the
     *                                   transaction is not in a refundable
     *                                   state, it carries no (valid)
     *                                   Flutterwave transaction id, or the
     *                                   order currency cannot be resolved.
     */
    public function resolve(OrderTransactionEntity $transaction, ?string $salesChannelId): RefundContext
    {
        if (!$this->config->getBool('refundEnabled', $salesChannelId)) {
            throw new RefundValidationException('Refund feature is currently disabled');
        }

        if (!$this->isRefundable($transaction)) {
            throw new RefundValidationException('Transaction is not in a refundable state');
        }

        $flutterwaveTransactionId = FlutterwaveTransactionReader::transactionId($transaction);

        if ($flutterwaveTransactionId === null) {
            throw new RefundValidationException(
                'This transaction has no Flutterwave transaction id and cannot be refunded'
            );
        }

        $currencyIso = OrderCurrencyResolver::resolve($transaction);

        if ($currencyIso === null) {
            throw new RefundValidationException('Unable to resolve the order currency for this transaction');
        }

        return new RefundContext(
            $flutterwaveTransactionId,
            $currencyIso,
            FlutterwaveTransactionReader::chargedAmount($transaction),
            $this->minimumAmount($currencyIso, $salesChannelId),
            $salesChannelId
        );
    }

    private function isRefundable(OrderTransactionEntity $transaction): bool
    {
        $state = $transaction->getStateMachineState()?->getTechnicalName();

        return in_array($state, self::REFUNDABLE_STATES, true);
    }

    /**
     * The smallest refund allowed, in major units.
     *
     * A merchant-configured value always wins; otherwise this falls back to
     * Flutterwave's own documented per-currency floor (below which
     * Flutterwave rejects the refund outright), and finally to 0 for a
     * currency with no known floor rather than blocking refunds on it.
     */
    private function minimumAmount(string $currencyIso, ?string $salesChannelId): float
    {
        $configured = $this->config->get('minimumRefundAmount', null, $salesChannelId);

        if (is_numeric($configured) && (float)$configured > 0.0) {
            return (float)$configured;
        }

        return FlutterwaveConstants::MINIMUM_REFUND_AMOUNT[$currencyIso] ?? 0.0;
    }
}
