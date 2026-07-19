<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Client\Resource;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Builds ResponseInterface stubs shaped like real Flutterwave replies.
 *
 * MockResponse has to be wrapped via fromRequest() to be a usable
 * ResponseInterface outside of MockHttpClient.
 */
trait MocksFlutterwaveResponses
{
    /**
     * @param array<string, mixed>|string $body
     */
    private function respondWith(array|string $body, int $status = 200): ResponseInterface
    {
        $content = is_string($body) ? $body : (string)json_encode($body);

        return MockResponse::fromRequest(
            'GET',
            'https://api.flutterwave.com/v3/x',
            [],
            new MockResponse($content, [
                'http_code' => $status,
                'response_headers' => ['content-type' => 'application/json'],
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function successBody(): array
    {
        return ['status' => 'success', 'data' => []];
    }
}
