<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Checkout\Payment;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\AbstractPaymentHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Framework\Context;

class AbstractPaymentHandlerTest extends TestCase
{
    public function testSupportsReturnsFalse(): void
    {
        $handler = new class() extends AbstractPaymentHandler {
            public function pay(\Symfony\Component\HttpFoundation\Request $request, \Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct $transaction, Context $context, ?\Shopware\Core\Framework\Struct\Struct $validateStruct): ?\Symfony\Component\HttpFoundation\RedirectResponse
            {
                return null;
            }
            public function finalize(\Symfony\Component\HttpFoundation\Request $request, \Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct $transaction, Context $context): void
            {
            }
        };

        $this->assertFalse($handler->supports(PaymentHandlerType::RECURRING, 'method-id', Context::createDefaultContext()));
    }
}
