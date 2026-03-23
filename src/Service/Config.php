<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Config service handles retrieval of plugin settings from Shopware's system configuration.
 */
class Config
{
    private const CONFIG_PATH = 'KommandhubFlutterwaveV3SW.config.';

    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * Returns the configured Flutterwave API Public Key.
     * Switch between sandbox and live keys based on the environment setting.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return string|null The API Public Key.
     */
    public function getApiPublicKey(?string $salesChannelId = null): ?string
    {
        if ($this->isSandbox($salesChannelId)) {
            return $this->systemConfigService->getString(self::CONFIG_PATH . 'apiPublicKeySandbox', $salesChannelId);
        }

        return $this->systemConfigService->getString(self::CONFIG_PATH . 'apiPublicKey', $salesChannelId);
    }

    /**
     * Returns the configured Flutterwave API Secret Key.
     * Switch between sandbox and live keys based on the environment setting.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return string|null The API Secret Key.
     */
    public function getApiSecretKey(?string $salesChannelId = null): ?string
    {
        if ($this->isSandbox($salesChannelId)) {
            return $this->systemConfigService->getString(self::CONFIG_PATH . 'apiSecretKeySandbox', $salesChannelId);
        }

        return $this->systemConfigService->getString(self::CONFIG_PATH . 'apiSecretKey', $salesChannelId);
    }

    /**
     * Returns the configured Flutterwave API Encryption Key.
     * Switch between sandbox and live keys based on the environment setting.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return string|null The API Encryption Key.
     */
    public function getApiEncryptionKey(?string $salesChannelId = null): ?string
    {
        if ($this->isSandbox($salesChannelId)) {
            return $this->systemConfigService->getString(self::CONFIG_PATH . 'apiEncryptionKeySandbox', $salesChannelId);
        }

        return $this->systemConfigService->getString(self::CONFIG_PATH . 'apiEncryptionKey', $salesChannelId);
    }

    /**
     * Checks if the plugin is in sandbox (test) mode.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return bool True if sandbox mode is enabled, false otherwise.
     */
    public function isSandbox(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(self::CONFIG_PATH . 'enableSandbox', $salesChannelId);
    }

    /**
     * Returns the payment method title displayed to the customer.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return string The configured title or a default value.
     */
    public function getTitle(?string $salesChannelId = null): string
    {
        return $this->systemConfigService->getString(self::CONFIG_PATH . 'title', $salesChannelId) ?: 'Flutterwave Standard Payment';
    }

    /**
     * Returns the payment method description displayed to the customer.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return string The configured description or a default value.
     */
    public function getDescription(?string $salesChannelId = null): string
    {
        return $this->systemConfigService->getString(self::CONFIG_PATH . 'description', $salesChannelId) ?: 'Developed with ❤️ by Kommandhub';
    }

    /**
     * Returns the logo URL for the payment customization.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return string|null The logo URL.
     */
    public function getLogo(?string $salesChannelId = null): ?string
    {
        return $this->systemConfigService->getString(self::CONFIG_PATH . 'logo', $salesChannelId);
    }

    /**
     * Checks if debug logging is enabled.
     *
     * @param string|null $salesChannelId The sales channel ID for scoped configuration.
     *
     * @return bool True if debug logging is enabled, false otherwise.
     */
    public function isDebugEnabled(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(self::CONFIG_PATH . 'enableDebugging', $salesChannelId);
    }
}
