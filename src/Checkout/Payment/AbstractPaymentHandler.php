<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler as ShopwareAbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Framework\Context;

abstract class AbstractPaymentHandler extends ShopwareAbstractPaymentHandler
{
    /**
     * Determines if the payment handler supports the given payment type.
     *
     * @param PaymentHandlerType $type
     * @param string $paymentMethodId
     * @param Context $context
     *
     * @return bool Always returns false in the base implementation.
     */
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }
}
