<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Logging;

use Kommandhub\FlutterwaveSW\Setting\Service\Config;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Configurable logger wrapper.
 *
 * The single place that decides whether a Flutterwave log entry is written.
 * Callers just log at the appropriate PSR-3 level and let this class apply the
 * merchant's configuration:
 *
 * - `enableDebugging` turns informational output on or off.
 * - `logLevels` narrows which PSR-3 levels are recorded when it is on.
 * - Failures (error and above) are always recorded regardless of both.
 *
 * Callers must not re-implement any of this. The previous handler wrapped every
 * call in its own `isDebugEnabled()` check, which both duplicated the gate and
 * — because it wrapped `logger->error()` too — discarded every payment failure
 * in production, the exact opposite of what the debug toggle should mean.
 */
class ConfigurableLogger extends AbstractLogger
{
    /**
     * Context key carrying the sales channel a log entry belongs to.
     *
     * `enableDebugging` and `logLevels` are ordinary plugin settings, so Shopware
     * lets a merchant scope them to a single sales channel. PSR-3 has no
     * argument for that, so callers pass the id in the log context and this
     * class resolves the configuration against it. Entries without the key fall
     * back to the global scope.
     *
     * The key is left in the forwarded context on purpose: it is useful
     * structured data on the entry itself.
     */
    public const CONTEXT_SALES_CHANNEL_ID = 'salesChannelId';

    /**
     * Severity levels always written, regardless of the debug toggle or the
     * configured levels, so production keeps a trail of failures (webhook
     * signature rejections, verification and refund errors).
     */
    private const ALWAYS_LOGGED = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    /**
     * @param mixed $level PSR-3 log level
     * @param string|Stringable $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelString = is_scalar($level) || $level instanceof Stringable ? (string)$level : 'unknown';

        if (!$this->shouldLog($levelString, $this->resolveSalesChannelId($context))) {
            return;
        }

        $this->logger->log($levelString, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveSalesChannelId(array $context): ?string
    {
        $salesChannelId = $context[self::CONTEXT_SALES_CHANNEL_ID] ?? null;

        return is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null;
    }

    private function shouldLog(string $level, ?string $salesChannelId): bool
    {
        if (in_array($level, self::ALWAYS_LOGGED, true)) {
            return true;
        }

        return $this->isLoggingEnabled($salesChannelId)
            && $this->isLevelAllowed($level, $salesChannelId);
    }

    private function isLoggingEnabled(?string $salesChannelId): bool
    {
        return $this->config->getBool('enableDebugging', $salesChannelId);
    }

    /**
     * When no levels are configured, all are allowed. This keeps installations
     * that enabled debugging without explicitly selecting levels behaving as
     * they did before the setting existed.
     */
    private function isLevelAllowed(string $level, ?string $salesChannelId): bool
    {
        $allowedLevels = $this->config->getArray('logLevels', $salesChannelId);

        return empty($allowedLevels) || in_array($level, $allowedLevels, true);
    }
}
