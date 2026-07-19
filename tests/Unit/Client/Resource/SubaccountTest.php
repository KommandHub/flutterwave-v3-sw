<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Client\Http\HttpClientInterface;
use Kommandhub\FlutterwaveV3SW\Client\Resource\ApiResource;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Subaccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subaccount::class)]
#[UsesClass(ApiResource::class)]
class SubaccountTest extends TestCase
{
    use MocksFlutterwaveResponses;

    private HttpClientInterface&MockObject $httpClient;
    private Subaccount $subaccount;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->subaccount = new Subaccount($this->httpClient);
    }

    public function testCreatePostsPayload(): void
    {
        $payload = [
            'account_bank' => '044',
            'account_number' => '0690000032',
            'business_name' => 'Eatery',
            'split_value' => 0.5,
        ];

        $this->httpClient->expects(static::once())
            ->method('post')
            ->with('/subaccounts', $payload, 'sales-channel-id')
            ->willReturn($this->respondWith($this->successBody()));

        static::assertSame($this->successBody(), $this->subaccount->create($payload, 'sales-channel-id'));
    }

    public function testListForwardsQueryParameters(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/subaccounts', ['page' => 2], null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->subaccount->list(['page' => 2]);
    }

    public function testListDefaultsToNoParameters(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/subaccounts', [], null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->subaccount->list();
    }

    public function testFetchGetsASingleSubaccount(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/subaccounts/RS_123', [], null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->subaccount->fetch('RS_123');
    }

    public function testUpdatePutsPayload(): void
    {
        $this->httpClient->expects(static::once())
            ->method('put')
            ->with('/subaccounts/RS_123', ['split_value' => 0.3], null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->subaccount->update('RS_123', ['split_value' => 0.3]);
    }

    public function testDeleteSendsDelete(): void
    {
        $this->httpClient->expects(static::once())
            ->method('delete')
            ->with('/subaccounts/RS_123', null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->subaccount->delete('RS_123');
    }
}
