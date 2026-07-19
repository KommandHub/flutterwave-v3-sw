<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Client\Resource;

use Kommandhub\FlutterwaveSW\Client\Http\HttpClientInterface;
use Kommandhub\FlutterwaveSW\Client\Resource\ApiResource;
use Kommandhub\FlutterwaveSW\Client\Resource\Bank;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bank::class)]
#[UsesClass(ApiResource::class)]
class BankTest extends TestCase
{
    use MocksFlutterwaveResponses;

    private HttpClientInterface&MockObject $httpClient;
    private Bank $bank;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->bank = new Bank($this->httpClient);
    }

    public function testListGetsBanksForACountry(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/banks/NG', [], 'sales-channel-id')
            ->willReturn($this->respondWith($this->successBody()));

        static::assertSame($this->successBody(), $this->bank->list('NG', 'sales-channel-id'));
    }

    public function testListUppercasesTheCountryCode(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/banks/GH', [], null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->bank->list('gh');
    }

    public function testBranchesGetsBranchesForABank(): void
    {
        $this->httpClient->expects(static::once())
            ->method('get')
            ->with('/banks/280/branches', [], null)
            ->willReturn($this->respondWith($this->successBody()));

        $this->bank->branches('280');
    }

    /**
     * Flutterwave resolves accounts with a POST body, unlike Paystack's GET with
     * query parameters — and names the field `account_bank`, not `bank_code`.
     */
    public function testResolveAccountPostsAccountNumberAndBankCode(): void
    {
        $this->httpClient->expects(static::once())
            ->method('post')
            ->with('/accounts/resolve', [
                'account_number' => '0690000032',
                'account_bank' => '044',
            ], null)
            ->willReturn($this->respondWith([
                'status' => 'success',
                'data' => ['account_name' => 'Ada Lovelace'],
            ]));

        static::assertSame(
            ['status' => 'success', 'data' => ['account_name' => 'Ada Lovelace']],
            $this->bank->resolveAccount('0690000032', '044')
        );
    }

    public function testSupportedCountriesCoversFlutterwavesBankListMarkets(): void
    {
        static::assertSame(['NG', 'GH', 'KE', 'UG', 'ZA', 'TZ'], Bank::SUPPORTED_COUNTRIES);
    }
}
