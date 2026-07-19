<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Client\Http;

use Kommandhub\FlutterwaveV3SW\Exception\FlutterwaveException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Interface HttpClientInterface.
 */
interface HttpClientInterface
{
    /**
     * Send a GET request.
     *
     * @param array<string, mixed> $queryParams
     *
     * @throws FlutterwaveException
     */
    public function get(string $endpoint, array $queryParams = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a POST request.
     *
     * @param array<string, mixed> $payload
     *
     * @throws FlutterwaveException
     */
    public function post(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a PUT request.
     *
     * @param array<string, mixed> $payload
     *
     * @throws FlutterwaveException
     */
    public function put(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a DELETE request.
     *
     * @throws FlutterwaveException
     */
    public function delete(string $endpoint, ?string $salesChannelId = null): ResponseInterface;
}
