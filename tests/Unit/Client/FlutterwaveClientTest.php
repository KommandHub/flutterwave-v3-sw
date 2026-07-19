<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Client;

use Kommandhub\FlutterwaveV3SW\Client\FlutterwaveClient;
use Kommandhub\FlutterwaveV3SW\Client\Http\HttpClientInterface;
use Kommandhub\FlutterwaveV3SW\Client\Resource\ApiResource;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Bank;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Bvn;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Refund;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Subaccount;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Transaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlutterwaveClient::class)]
#[UsesClass(ApiResource::class)]
class FlutterwaveClientTest extends TestCase
{
    private FlutterwaveClient $client;

    protected function setUp(): void
    {
        $this->client = new FlutterwaveClient($this->createMock(HttpClientInterface::class));
    }

    public function testTransactionsReturnsTransactionResource(): void
    {
        static::assertInstanceOf(Transaction::class, $this->client->transactions());
    }

    public function testRefundsReturnsRefundResource(): void
    {
        static::assertInstanceOf(Refund::class, $this->client->refunds());
    }

    public function testSubaccountsReturnsSubaccountResource(): void
    {
        static::assertInstanceOf(Subaccount::class, $this->client->subaccounts());
    }

    public function testBanksReturnsBankResource(): void
    {
        static::assertInstanceOf(Bank::class, $this->client->banks());
    }

    public function testBvnReturnsBvnResource(): void
    {
        static::assertInstanceOf(Bvn::class, $this->client->bvn());
    }
}
