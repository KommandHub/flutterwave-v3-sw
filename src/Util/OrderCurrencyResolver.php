<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Util;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * Resolves the ISO currency code of the order behind an order transaction.
 *
 * Amounts are compared by scaling to minor units with per-currency decimals, so
 * resolving the wrong currency changes the comparison by a factor of 10^n and can
 * make a mismatched amount look equal. Defaulting to a guess here caused a
 * production defect in the Paystack plugin (the webhook refund path resolved
 * every amount as NGN because the `order` association was not loaded), so this
 * resolver deliberately **fails closed**: callers must abort rather than assume.
 *
 * Callers are responsible for loading the `order.currency` association.
 */
final class OrderCurrencyResolver
{
    /**
     * @return string|null The ISO code, or null when it cannot be resolved
     *                     (order/currency association missing or not loaded).
     */
    public static function resolve(OrderTransactionEntity $transaction): ?string
    {
        return $transaction->getOrder()?->getCurrency()?->getIsoCode();
    }

    /**
     * @throws \RuntimeException When the currency cannot be resolved.
     */
    public static function resolveOrFail(OrderTransactionEntity $transaction): string
    {
        $isoCode = self::resolve($transaction);

        if ($isoCode === null) {
            throw new \RuntimeException(sprintf(
                'Unable to resolve the order currency for transaction "%s". '
                . 'Refusing to compare amounts with an assumed currency; '
                . 'ensure the "order.currency" association is loaded.',
                $transaction->getId()
            ));
        }

        return $isoCode;
    }
}
