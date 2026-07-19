<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Client\Http;

use Kommandhub\FlutterwaveSW\Client\Http\FlutterwaveHttpClient;
use Kommandhub\FlutterwaveSW\Exception\FlutterwaveException;
use Kommandhub\FlutterwaveSW\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;

#[CoversClass(FlutterwaveHttpClient::class)]
class FlutterwaveHttpClientTest extends TestCase
{
    private Config&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getSecretKey')->willReturn('FLWSECK_TEST-abc');
    }

    /**
     * @param callable(\Symfony\Component\HttpClient\Response\MockResponse=): mixed $assert
     */
    private function createClient(callable $onRequest): FlutterwaveHttpClient
    {
        return new FlutterwaveHttpClient($this->config, new MockHttpClient($onRequest));
    }

    public function testGetSendsAuthorisedRequestToTheV3BaseUrl(): void
    {
        $client = $this->createClient(function (string $method, string $url, array $options): MockResponse {
            static::assertSame('GET', $method);
            static::assertStringStartsWith('https://api.flutterwave.com/v3/transactions/1/verify', $url);
            static::assertContains('Authorization: Bearer FLWSECK_TEST-abc', $options['headers']);
            static::assertContains('Content-Type: application/json', $options['headers']);

            return new MockResponse('{"status":"success"}');
        });

        static::assertSame(200, $client->get('/transactions/1/verify')->getStatusCode());
    }

    public function testGetForwardsQueryParameters(): void
    {
        $client = $this->createClient(function (string $method, string $url): MockResponse {
            static::assertStringContainsString('tx_ref=abc-123', $url);

            return new MockResponse('{}');
        });

        $client->get('/transactions/verify_by_reference', ['tx_ref' => 'abc-123']);
    }

    public function testPostSendsJsonBody(): void
    {
        $client = $this->createClient(function (string $method, string $url, array $options): MockResponse {
            static::assertSame('POST', $method);
            static::assertSame('https://api.flutterwave.com/v3/payments', $url);
            static::assertSame('{"amount":100,"currency":"NGN"}', $options['body']);

            return new MockResponse('{}');
        });

        $client->post('/payments', ['amount' => 100, 'currency' => 'NGN']);
    }

    public function testPutSendsJsonBody(): void
    {
        $client = $this->createClient(function (string $method, string $url, array $options): MockResponse {
            static::assertSame('PUT', $method);
            static::assertSame('https://api.flutterwave.com/v3/subaccounts/1', $url);
            static::assertSame('{"split_value":0.5}', $options['body']);

            return new MockResponse('{}');
        });

        $client->put('/subaccounts/1', ['split_value' => 0.5]);
    }

    public function testDeleteSendsRequest(): void
    {
        $client = $this->createClient(function (string $method, string $url, array $options): MockResponse {
            static::assertSame('DELETE', $method);
            static::assertSame('https://api.flutterwave.com/v3/subaccounts/1', $url);
            static::assertContains('Authorization: Bearer FLWSECK_TEST-abc', $options['headers']);

            return new MockResponse('', ['http_code' => 204]);
        });

        static::assertSame(204, $client->delete('/subaccounts/1')->getStatusCode());
    }

    public function testSalesChannelIdIsForwardedToConfig(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(static::once())
            ->method('getSecretKey')
            ->with('sales-channel-id')
            ->willReturn('FLWSECK-live');

        $client = new FlutterwaveHttpClient($config, new MockHttpClient(new MockResponse('{}')));
        $client->get('/transactions', [], 'sales-channel-id');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function methodProvider(): array
    {
        return [
            'get' => ['get'],
            'post' => ['post'],
            'put' => ['put'],
            'delete' => ['delete'],
        ];
    }

    /**
     * Fail fast and legibly on an unconfigured plugin, rather than sending
     * "Bearer " and letting Flutterwave answer with an opaque 401.
     */
    #[DataProvider('methodProvider')]
    public function testMissingSecretKeyThrowsBeforeAnyRequestIsSent(string $method): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getSecretKey')->willReturn('');

        $httpClient = $this->createMock(SymfonyHttpClientInterface::class);
        $httpClient->expects(static::never())->method('request');

        $client = new FlutterwaveHttpClient($config, $httpClient);

        $this->expectException(FlutterwaveException::class);
        $this->expectExceptionMessage('Flutterwave secret key is not configured.');

        $client->{$method}('/transactions');
    }

    /**
     * Transport faults (DNS, TLS, timeouts) are wrapped so callers only ever
     * handle one exception type from this layer.
     */
    #[DataProvider('methodProvider')]
    public function testTransportFailureIsWrappedAsFlutterwaveException(string $method): void
    {
        $httpClient = $this->createMock(SymfonyHttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new TransportException('DNS failure'));

        $client = new FlutterwaveHttpClient($this->config, $httpClient);

        $this->expectException(FlutterwaveException::class);
        $this->expectExceptionMessage('DNS failure');

        $client->{$method}('/transactions');
    }
}
