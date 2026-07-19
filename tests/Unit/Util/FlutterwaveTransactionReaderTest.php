<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Util;

use Kommandhub\FlutterwaveV3SW\Util\FlutterwaveTransactionReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

#[CoversClass(FlutterwaveTransactionReader::class)]
class FlutterwaveTransactionReaderTest extends TestCase
{
    /**
     * @param array<string, mixed> $customFields
     */
    private function transaction(array $customFields, float $total = 100.0): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('order-transaction-id');
        $transaction->setCustomFields($customFields);
        $transaction->setAmount(new CalculatedPrice($total, $total, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $transaction;
    }

    public function testTransactionIdReturnsStoredNumericId(): void
    {
        $transaction = $this->transaction(['flutterwave_transaction_id' => '12345']);

        static::assertSame('12345', FlutterwaveTransactionReader::transactionId($transaction));
    }

    public function testTransactionIdReturnsNullWhenMissing(): void
    {
        static::assertNull(FlutterwaveTransactionReader::transactionId($this->transaction([])));
    }

    /**
     * The Shopware order_transaction id is a hex UUID, never numeric; a stray
     * one in this custom field must never be handed to Flutterwave as if it
     * were a transaction id.
     */
    public function testTransactionIdRejectsNonNumericValue(): void
    {
        $transaction = $this->transaction(['flutterwave_transaction_id' => '019f6531ce1c73149ce304caf2fe2ecf']);

        static::assertNull(FlutterwaveTransactionReader::transactionId($transaction));
    }

    public function testChargedAmountReturnsStoredValue(): void
    {
        $transaction = $this->transaction(['flutterwave_amount_charged' => 75.5], total: 100.0);

        static::assertSame(75.5, FlutterwaveTransactionReader::chargedAmount($transaction));
    }

    public function testChargedAmountFallsBackToTransactionTotalWhenMissing(): void
    {
        $transaction = $this->transaction([], total: 42.0);

        static::assertSame(42.0, FlutterwaveTransactionReader::chargedAmount($transaction));
    }
}
