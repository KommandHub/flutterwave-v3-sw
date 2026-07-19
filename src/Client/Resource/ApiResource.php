<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Client\Http\HttpClientInterface;
use Kommandhub\FlutterwaveV3SW\Exception\FlutterwaveException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class ApiResource.
 */
abstract class ApiResource
{
    public function __construct(protected HttpClientInterface $httpClient)
    {
    }

    /**
     * Parse the response from the API.
     *
     * Flutterwave answers errors with a normal JSON body — `{"status": "error",
     * "message": "...", "data": null}` — alongside a 4xx status. `toArray()`
     * throws on non-2xx by default, which would discard that body and leave
     * callers with an opaque HTTP exception, making every `status === 'error'`
     * branch unreachable and hiding Flutterwave's actionable message from the
     * merchant. Pass `false` so the body always survives.
     *
     * Transport failures (DNS, timeout, TLS) and non-JSON bodies are real faults
     * and surface as FlutterwaveException.
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    protected function response(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (\Throwable $exception) {
            throw new FlutterwaveException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }
}
