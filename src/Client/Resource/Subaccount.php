<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Exception\FlutterwaveException;

/**
 * Class Subaccount.
 *
 * Backs split payments: a Standard checkout payload may carry a `subaccounts`
 * array to route a share of the settlement elsewhere.
 *
 * @see https://developer.flutterwave.com/v3.0/docs/split-payments
 */
class Subaccount extends ApiResource
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function create(array $payload, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->post('/subaccounts', $payload, $salesChannelId));
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function list(array $queryParams = [], ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get('/subaccounts', $queryParams, $salesChannelId));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function fetch(string $id, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get("/subaccounts/{$id}", [], $salesChannelId));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function update(string $id, array $payload, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->put("/subaccounts/{$id}", $payload, $salesChannelId));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function delete(string $id, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->delete("/subaccounts/{$id}", $salesChannelId));
    }
}
