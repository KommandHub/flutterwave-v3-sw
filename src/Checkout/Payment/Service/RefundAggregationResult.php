<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service;

/**
 * @final
 */
final readonly class RefundAggregationResult
{
    /**
     * @param array<string, object> $captures
     * @param bool $isFullyRefunded
     */
    public function __construct(
        public array $captures,
        public bool $isFullyRefunded,
    ) {
    }
}
