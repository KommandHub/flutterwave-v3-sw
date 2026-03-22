<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Service;

use Kommandhub\FlutterwaveV3SW\Service\Config;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigTest extends TestCase
{
    private SystemConfigService $systemConfigService;
    private Config $config;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->config = new Config($this->systemConfigService);
    }

    public function testGetApiPublicKeyInSandboxMode(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableSandbox', $salesChannelId)
            ->willReturn(true);

        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.apiPublicKeySandbox', $salesChannelId)
            ->willReturn('pk_sandbox_key');

        $this->assertEquals('pk_sandbox_key', $this->config->getApiPublicKey($salesChannelId));
    }

    public function testGetApiPublicKeyInLiveMode(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableSandbox', $salesChannelId)
            ->willReturn(false);

        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.apiPublicKey', $salesChannelId)
            ->willReturn('pk_live_key');

        $this->assertEquals('pk_live_key', $this->config->getApiPublicKey($salesChannelId));
    }

    public function testIsSandbox(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableSandbox', $salesChannelId)
            ->willReturn(true);

        $this->assertTrue($this->config->isSandbox($salesChannelId));
    }

    public function testIsDebugEnabled(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableDebugging', $salesChannelId)
            ->willReturn(true);

        $this->assertTrue($this->config->isDebugEnabled($salesChannelId));
    }

    public function testGetTitleReturnsDefaultIfEmpty(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.title', $salesChannelId)
            ->willReturn('');

        $this->assertEquals('Flutterwave Standard Payment', $this->config->getTitle($salesChannelId));
    }

    public function testGetApiSecretKeyInSandboxMode(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableSandbox', $salesChannelId)
            ->willReturn(true);

        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.apiSecretKeySandbox', $salesChannelId)
            ->willReturn('sk_sandbox_key');

        $this->assertEquals('sk_sandbox_key', $this->config->getApiSecretKey($salesChannelId));
    }

    public function testGetApiSecretKeyInLiveMode(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableSandbox', $salesChannelId)
            ->willReturn(false);

        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.apiSecretKey', $salesChannelId)
            ->willReturn('sk_live_key');

        $this->assertEquals('sk_live_key', $this->config->getApiSecretKey($salesChannelId));
    }

    public function testGetApiEncryptionKeyInSandboxMode(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableSandbox', $salesChannelId)
            ->willReturn(true);

        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.apiEncryptionKeySandbox', $salesChannelId)
            ->willReturn('ek_sandbox_key');

        $this->assertEquals('ek_sandbox_key', $this->config->getApiEncryptionKey($salesChannelId));
    }

    public function testGetApiEncryptionKeyInLiveMode(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KommandhubFlutterwaveV3SW.config.enableSandbox', $salesChannelId)
            ->willReturn(false);

        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.apiEncryptionKey', $salesChannelId)
            ->willReturn('ek_live_key');

        $this->assertEquals('ek_live_key', $this->config->getApiEncryptionKey($salesChannelId));
    }

    public function testGetTitle(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.title', $salesChannelId)
            ->willReturn('Custom Title');

        $this->assertEquals('Custom Title', $this->config->getTitle($salesChannelId));
    }

    public function testGetDescription(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->exactly(2))
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.description', $salesChannelId)
            ->willReturnOnConsecutiveCalls('Custom Description', '');

        $this->assertEquals('Custom Description', $this->config->getDescription($salesChannelId));
        $this->assertEquals('Developed with ❤️ by Kommandhub', $this->config->getDescription($salesChannelId));
    }

    public function testGetLogo(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $this->systemConfigService->expects($this->once())
            ->method('getString')
            ->with('KommandhubFlutterwaveV3SW.config.logo', $salesChannelId)
            ->willReturn('https://example.com/logo.png');

        $this->assertEquals('https://example.com/logo.png', $this->config->getLogo($salesChannelId));
    }
}
