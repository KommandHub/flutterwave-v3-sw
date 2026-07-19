<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Checkout\Payment\Service;

use Kommandhub\FlutterwaveSW\Checkout\Payment\Service\FlutterwaveRefundLedger;
use Kommandhub\FlutterwaveSW\Client\FlutterwaveClient;
use Kommandhub\FlutterwaveSW\Client\Resource\Transaction;
use Kommandhub\FlutterwaveSW\Util\FlutterwaveCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlutterwaveRefundLedger::class)]
#[UsesClass(FlutterwaveCurrencyHelper::class)]
class FlutterwaveRefundLedgerTest extends TestCase
{
    private Transaction&MockObject $transactionResource;
    private FlutterwaveRefundLedger $ledger;

    protected function setUp(): void
    {
        $this->transactionResource = $this->createMock(Transaction::class);
        $flutterwave = $this->createMock(FlutterwaveClient::class);
        $flutterwave->method('transactions')->willReturn($this->transactionResource);

        $this->ledger = new FlutterwaveRefundLedger($flutterwave);
    }

    /**
     * Flutterwave's `GET /refunds?id=` is documented to filter by transaction
     * id but in practice returns the whole account's refunds; only entries
     * whose `tx_id` matches must survive.
     */
    public function testRefundsForTransactionFiltersByTxIdClientSide(): void
    {
        $this->transactionResource->method('refunds')->willReturn(['status' => 'success', 'data' => [
            ['id' => 1, 'tx_id' => 12345],
            ['id' => 2, 'tx_id' => 99999],
            ['id' => 3],
            'not-an-array',
        ]]);

        $result = $this->ledger->refundsForTransaction('12345', 'sales-channel-id');

        static::assertCount(1, $result);
        static::assertSame(1, $result[0]['id']);
    }

    public function testRefundsForTransactionReturnsEmptyArrayWhenDataMissing(): void
    {
        $this->transactionResource->method('refunds')->willReturn(['status' => 'success']);

        static::assertSame([], $this->ledger->refundsForTransaction('12345', null));
    }

    public function testAlreadyRefundedMinorSumsNonFailedRefunds(): void
    {
        $this->transactionResource->method('refunds')->willReturn(['status' => 'success', 'data' => [
            ['tx_id' => 12345, 'status' => 'completed', 'amount_refunded' => 30],
            ['tx_id' => 12345, 'status' => 'successful', 'amount_refunded' => 20],
        ]]);

        static::assertSame(5000, $this->ledger->alreadyRefundedMinor('12345', 'NGN', null));
    }

    /**
     * A failed refund frees its amount back into the refundable balance, so it
     * must not be counted — this is the denylist behaviour the class relies on
     * to fail safe on money (an allowlist that omitted a real "in progress"
     * status would under-count and allow an over-refund).
     */
    public function testAlreadyRefundedMinorExcludesFailedRefunds(): void
    {
        $this->transactionResource->method('refunds')->willReturn(['status' => 'success', 'data' => [
            ['tx_id' => 12345, 'status' => 'failed', 'amount_refunded' => 100],
        ]]);

        static::assertSame(0, $this->ledger->alreadyRefundedMinor('12345', 'NGN', null));
    }

    public function testAlreadyRefundedMinorIgnoresMalformedEntries(): void
    {
        $this->transactionResource->method('refunds')->willReturn(['status' => 'success', 'data' => [
            'not-an-array',
            ['tx_id' => 12345, 'status' => 'completed', 'amount_refunded' => 'not-numeric'],
            ['tx_id' => 12345, 'status' => 'completed', 'amount_refunded' => 20],
        ]]);

        static::assertSame(2000, $this->ledger->alreadyRefundedMinor('12345', 'NGN', null));
    }

    public function testAlreadyRefundedMinorFallsBackToAmountFieldWhenAmountRefundedMissing(): void
    {
        $this->transactionResource->method('refunds')->willReturn(['status' => 'success', 'data' => [
            ['tx_id' => 12345, 'status' => 'completed', 'amount' => 15],
        ]]);

        static::assertSame(1500, $this->ledger->alreadyRefundedMinor('12345', 'NGN', null));
    }

    public function testAlreadyRefundedMinorExcludesForeignTransactionRefunds(): void
    {
        $this->transactionResource->method('refunds')->willReturn(['status' => 'success', 'data' => [
            ['tx_id' => 99999, 'status' => 'completed', 'amount_refunded' => 100],
        ]]);

        static::assertSame(0, $this->ledger->alreadyRefundedMinor('12345', 'NGN', null));
    }
}
