<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Cart\Validation;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\FlutterwaveTransactionHandler;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Validates the cart for the Standard Payment method.
 *
 * This validator checks if the selected payment method is the StandardPaymentHandler.
 * If so, it uses the CartPriceService to determine if the cart has a zero value.
 * If the cart value is zero, it adds a PaymentMethodBlockedError to the error collection,
 * preventing the use of this payment method for zero-value carts.
 */
#[AutoconfigureTag('shopware.cart.validator')]
class TransactionCartValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        // Check if the selected payment method is not the FlutterwaveTransactionHandler; if so, skip validation.
        if ($context->getPaymentMethod()->getHandlerIdentifier() !== FlutterwaveTransactionHandler::class) {
            return;
        }

        // If the cart value is zero, add a PaymentMethodBlockedError to prevent using this payment method.
        if ($this->isZeroValueCart($cart)) {
            /** @var string $name */
            $name = $context->getPaymentMethod()->getTranslation('name') ?? '';
            $errors->add(new PaymentMethodBlockedError($name));
        }
    }

    /**
     * Determines if the cart has items but the total price is zero or less.
     *
     * @param Cart $cart The cart to check.
     *
     * @return bool True if the cart has items and the total price is zero or less, false otherwise.
     */
    public function isZeroValueCart(Cart $cart): bool
    {
        // Return false if the cart has no items.
        if ($cart->getLineItems()->count() === 0) {
            return false;
        }

        // Return false if the cart's total price is greater than zero.
        if ($cart->getPrice()->getTotalPrice() > 0) {
            return false;
        }

        // Return true if the cart has items and the total price is zero or less.
        return true;
    }
}