<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Setting\Service;

use Kommandhub\FlutterwaveV3SW\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;
    private Config $config;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->config = new Config($this->systemConfigService);
    }

    public function testGetReturnsValue(): void
    {
        $this->systemConfigService->method('get')
            ->with(Config::KEY . 'title', 'sales-channel-id')
            ->willReturn('Flutterwave');

        static::assertSame('Flutterwave', $this->config->get('title', 'fallback', 'sales-channel-id'));
    }

    public function testGetFallsBackToDefaultWhenUnset(): void
    {
        $this->systemConfigService->method('get')->willReturn(null);

        static::assertSame('fallback', $this->config->get('title', 'fallback'));
    }

    public function testGetStringReturnsValue(): void
    {
        $this->systemConfigService->method('get')->willReturn('Flutterwave');

        static::assertSame('Flutterwave', $this->config->getString('title'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonStringProvider(): array
    {
        return [
            'null' => [null],
            'int' => [42],
            'array' => [['a']],
            'bool' => [true],
        ];
    }

    #[DataProvider('nonStringProvider')]
    public function testGetStringCoercesNonStringsToEmpty(mixed $value): void
    {
        $this->systemConfigService->method('get')->willReturn($value);

        static::assertSame('', $this->config->getString('title'));
    }

    public function testGetBoolDelegates(): void
    {
        $this->systemConfigService->method('getBool')
            ->with(Config::KEY . 'enableSandbox', null)
            ->willReturn(true);

        static::assertTrue($this->config->getBool('enableSandbox'));
    }

    public function testGetIntDelegates(): void
    {
        $this->systemConfigService->method('getInt')
            ->with(Config::KEY . 'sessionDuration', null)
            ->willReturn(30);

        static::assertSame(30, $this->config->getInt('sessionDuration'));
    }

    public function testGetArrayReturnsValue(): void
    {
        $this->systemConfigService->method('get')->willReturn(['card', 'ussd']);

        static::assertSame(['card', 'ussd'], $this->config->getArray('paymentOptions'));
    }

    public function testGetArrayCoercesNonArraysToEmpty(): void
    {
        $this->systemConfigService->method('get')->willReturn('card');

        static::assertSame([], $this->config->getArray('paymentOptions'));
    }

    public function testIsSandboxDelegates(): void
    {
        $this->systemConfigService->method('getBool')
            ->with(Config::KEY . 'enableSandbox', null)
            ->willReturn(true);

        static::assertTrue($this->config->isSandbox());
    }

    /**
     * The sandbox toggle must select the credential; using a live key in test
     * mode (or the reverse) is a real merchant-facing failure.
     */
    public function testGetSecretKeyUsesSandboxKeyInSandboxMode(): void
    {
        $this->systemConfigService->method('getBool')->willReturn(true);
        $this->systemConfigService->expects(static::once())
            ->method('get')
            ->with(Config::KEY . 'apiSecretKeySandbox', null)
            ->willReturn('FLWSECK_TEST-abc');

        static::assertSame('FLWSECK_TEST-abc', $this->config->getSecretKey());
    }

    public function testGetSecretKeyUsesLiveKeyInLiveMode(): void
    {
        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->systemConfigService->expects(static::once())
            ->method('get')
            ->with(Config::KEY . 'apiSecretKey', null)
            ->willReturn('FLWSECK-live');

        static::assertSame('FLWSECK-live', $this->config->getSecretKey());
    }

    public function testGetPublicKeyUsesSandboxKeyInSandboxMode(): void
    {
        $this->systemConfigService->method('getBool')->willReturn(true);
        $this->systemConfigService->expects(static::once())
            ->method('get')
            ->with(Config::KEY . 'apiPublicKeySandbox', null)
            ->willReturn('FLWPUBK_TEST-abc');

        static::assertSame('FLWPUBK_TEST-abc', $this->config->getPublicKey());
    }

    public function testGetPublicKeyUsesLiveKeyInLiveMode(): void
    {
        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->systemConfigService->expects(static::once())
            ->method('get')
            ->with(Config::KEY . 'apiPublicKey', null)
            ->willReturn('FLWPUBK-live');

        static::assertSame('FLWPUBK-live', $this->config->getPublicKey());
    }

    /**
     * Flutterwave lets merchants set a different secret hash per environment, so
     * the sandbox toggle must switch this too — reading the live hash while in
     * test mode rejects every genuine test webhook.
     */
    public function testGetSecretHashUsesSandboxHashInSandboxMode(): void
    {
        $this->systemConfigService->method('getBool')->willReturn(true);
        $this->systemConfigService->expects(static::once())
            ->method('get')
            ->with(Config::KEY . 'secretHashSandbox', null)
            ->willReturn('test-hash');

        static::assertSame('test-hash', $this->config->getSecretHash());
    }

    public function testGetSecretHashUsesLiveHashInLiveMode(): void
    {
        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->systemConfigService->expects(static::once())
            ->method('get')
            ->with(Config::KEY . 'secretHash', null)
            ->willReturn('live-hash');

        static::assertSame('live-hash', $this->config->getSecretHash());
    }

    public function testSalesChannelIdIsForwarded(): void
    {
        $this->systemConfigService->method('getBool')->willReturn(false);
        $this->systemConfigService->expects(static::once())
            ->method('get')
            ->with(Config::KEY . 'apiSecretKey', 'sales-channel-id')
            ->willReturn('FLWSECK-live');

        static::assertSame('FLWSECK-live', $this->config->getSecretKey('sales-channel-id'));
    }

    public function testGetTitleReturnsConfiguredValue(): void
    {
        $this->systemConfigService->method('get')
            ->with(Config::KEY . 'title', null)
            ->willReturn('Custom Title');

        static::assertSame('Custom Title', $this->config->getTitle());
    }

    public function testGetTitleFallsBackToDefaultWhenUnset(): void
    {
        $this->systemConfigService->method('get')->willReturn(null);

        static::assertSame('Pay with Flutterwave', $this->config->getTitle());
    }

    public function testGetDescriptionReturnsConfiguredValue(): void
    {
        $this->systemConfigService->method('get')
            ->with(Config::KEY . 'description', null)
            ->willReturn('Custom Description');

        static::assertSame('Custom Description', $this->config->getDescription());
    }

    public function testGetDescriptionFallsBackToDefaultWhenUnset(): void
    {
        $this->systemConfigService->method('get')->willReturn(null);

        static::assertSame('Developed with ❤️ by Kommandhub', $this->config->getDescription());
    }

    public function testGetLogoReturnsConfiguredValue(): void
    {
        $this->systemConfigService->method('getString')
            ->with(Config::KEY . 'logo', null)
            ->willReturn('https://example.com/logo.png');

        static::assertSame('https://example.com/logo.png', $this->config->getLogo());
    }

    /**
     * Unlike getTitle()/getDescription(), an unset logo has no placeholder
     * fallback — SystemConfigService::getString() itself coerces an unset
     * value to '', so that is what an unconfigured logo resolves to.
     */
    public function testGetLogoReturnsEmptyStringWhenUnset(): void
    {
        $this->systemConfigService->method('getString')->willReturn('');

        static::assertSame('', $this->config->getLogo());
    }
}
