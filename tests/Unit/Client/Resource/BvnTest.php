<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Client\Http\HttpClientInterface;
use Kommandhub\FlutterwaveV3SW\Client\Resource\ApiResource;
use Kommandhub\FlutterwaveV3SW\Client\Resource\Bvn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bvn::class)]
#[UsesClass(ApiResource::class)]
class BvnTest extends TestCase
{
    use MocksFlutterwaveResponses;

    private HttpClientInterface&MockObject $httpClient;
    private Bvn $bvn;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->bvn = new Bvn($this->httpClient);
    }

    public function testInitiatePostsConsentPayload(): void
    {
        $this->httpClient->expects(static::once())
            ->method('post')
            ->with('/bvn/verifications', [
                'bvn' => '12345678901',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                'redirect_url' => 'https://shop.example/callback',
            ], 'sales-channel-id')
            ->willReturn($this->respondWith([
                'status' => 'success',
                'data' => ['url' => 'https://flutterwave.com/consent', 'reference' => 'ref-1'],
            ]));

        static::assertSame(
            ['status' => 'success', 'data' => ['url' => 'https://flutterwave.com/consent', 'reference' => 'ref-1']],
            $this->bvn->initiate('12345678901', 'Ada', 'Lovelace', 'https://shop.example/callback', 'sales-channel-id')
        );
    }

    public function testFetchReadsVerificationByReference(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/bvn/verifications/ref-1', [], null)
            ->willReturn($this->respondWith(['status' => 'success', 'data' => ['status' => 'COMPLETED']]));

        static::assertSame(
            ['status' => 'success', 'data' => ['status' => 'COMPLETED']],
            $this->bvn->fetch('ref-1')
        );
    }
}
