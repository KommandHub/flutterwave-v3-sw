<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Service;

use Kommandhub\Flutterwave\Flutterwave;
use Kommandhub\Flutterwave\Resources\Transactions;

class TransactionFactory
{
    public function create(Flutterwave $client): Transactions
    {
        return $client->transactions();
    }
}
