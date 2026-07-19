<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Client;

use Kommandhub\FlutterwaveV3SW\Client\Http\HttpClientInterface;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Bank;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Bvn;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Refund;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Subaccount;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Transaction;

/**
 * Class FlutterwaveClient.
 *
 * Replaces the `kommandhub/flutterwave-v3` SDK wrapper. Only the resources this
 * plugin actually calls are exposed; more can be added as endpoints are needed.
 */
class FlutterwaveClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function transactions(): Transaction
    {
        return new Transaction($this->httpClient);
    }

    public function refunds(): Refund
    {
        return new Refund($this->httpClient);
    }

    public function subaccounts(): Subaccount
    {
        return new Subaccount($this->httpClient);
    }

    public function banks(): Bank
    {
        return new Bank($this->httpClient);
    }

    public function bvn(): Bvn
    {
        return new Bvn($this->httpClient);
    }
}
