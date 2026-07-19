<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Exception\FlutterwaveException;

/**
 * Class Refund.
 *
 * Refunds are *created* against a transaction (see Transaction::refund()),
 * because Flutterwave V3 exposes creation as POST /transactions/{id}/refund
 * rather than POST /refunds. This resource covers reading them back.
 *
 * @see https://developer.flutterwave.com/v3.0/docs/refunds
 */
class Refund extends ApiResource
{
    /**
     * List refunds.
     *
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function list(array $queryParams = [], ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get('/refunds', $queryParams, $salesChannelId));
    }

    /**
     * Fetch a single refund.
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function fetch(string $refundId, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get("/refunds/{$refundId}", [], $salesChannelId));
    }
}
