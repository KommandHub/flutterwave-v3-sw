<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Checkout\Payment\Struct;

use Kommandhub\FlutterwaveSW\Checkout\Payment\Struct\RefundContext;
use PHPUnit\Framework\TestCase;

class RefundContextTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = new RefundContext('12345', 'NGN', 100.0, 1.0, 'sales-channel-id');

        static::assertSame('12345', $context->flutterwaveTransactionId);
        static::assertSame('NGN', $context->currencyIso);
        static::assertSame(100.0, $context->chargedAmountMajor);
        static::assertSame(1.0, $context->minimumAmountMajor);
        static::assertSame('sales-channel-id', $context->salesChannelId);
    }
}
