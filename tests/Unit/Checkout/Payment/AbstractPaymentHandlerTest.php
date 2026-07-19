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
        $handler = $this->getHandler();

        $this->assertFalse($handler->supports(PaymentHandlerType::RECURRING, 'method-id', Context::createDefaultContext()));
    }

    public function testSupportsReturnsTrueForRefund(): void
    {
        $handler = $this->getHandler();

        $this->assertTrue($handler->supports(PaymentHandlerType::REFUND, 'method-id', Context::createDefaultContext()));
    }

    private function getHandler(): AbstractPaymentHandler
    {
        return new class() extends AbstractPaymentHandler {
            public function pay(\Symfony\Component\HttpFoundation\Request $request, \Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct $transaction, Context $context, ?\Shopware\Core\Framework\Struct\Struct $validateStruct): ?\Symfony\Component\HttpFoundation\RedirectResponse
            {
                return null;
            }
            public function finalize(\Symfony\Component\HttpFoundation\Request $request, \Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct $transaction, Context $context): void
            {
            }
        };
    }
}
