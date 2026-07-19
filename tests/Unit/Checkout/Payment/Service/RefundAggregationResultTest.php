<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Checkout\Payment\Service;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundAggregationResult;
use PHPUnit\Framework\TestCase;

class RefundAggregationResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $captures = [
            'capture-1' => (object)['isFullyRefunded' => true],
            'capture-2' => (object)['isFullyRefunded' => false],
        ];
        $isFullyRefunded = false;

        $result = new RefundAggregationResult($captures, $isFullyRefunded);

        static::assertSame($captures, $result->captures);
        static::assertSame($isFullyRefunded, $result->isFullyRefunded);
    }
}
