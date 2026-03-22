<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Service;

use Kommandhub\Flutterwave\Flutterwave;
use Kommandhub\Flutterwave\Resources\Transactions;
use Kommandhub\FlutterwaveV3SW\Service\TransactionFactory;
use PHPUnit\Framework\TestCase;

class TransactionFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new TransactionFactory();
        $client = $this->createMock(Flutterwave::class);
        $transactions = $this->createMock(Transactions::class);

        $client->expects($this->once())
            ->method('transactions')
            ->willReturn($transactions);

        $result = $factory->create($client);
        $this->assertSame($transactions, $result);
    }
}
