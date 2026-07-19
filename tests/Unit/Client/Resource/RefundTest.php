<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Client\Http\HttpClientInterface;
use Kommandhub\FlutterwaveV3SW\Client\Resource\ApiResource;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Refund::class)]
#[UsesClass(ApiResource::class)]
class RefundTest extends TestCase
{
    use MocksFlutterwaveResponses;

    private HttpClientInterface&MockObject $httpClient;
    private Refund $refund;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->refund = new Refund($this->httpClient);
    }

    public function testListForwardsQueryParameters(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/refunds', ['from' => '2026-01-01'], 'sales-channel-id')
            ->willReturn($this->respondWith($this->successBody()));

        static::assertSame($this->successBody(), $this->refund->list(['from' => '2026-01-01'], 'sales-channel-id'));
    }

    public function testListDefaultsToNoParameters(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/refunds', [], null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->refund->list();
    }

    public function testFetchGetsASingleRefund(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/refunds/98765', [], null)
            ->willReturn($this->respondWith(['status' => 'success', 'data' => ['id' => 98765]]));

        static::assertSame(
            ['status' => 'success', 'data' => ['id' => 98765]],
            $this->refund->fetch('98765')
        );
    }
}
