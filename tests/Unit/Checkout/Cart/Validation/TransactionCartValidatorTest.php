<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Checkout\Cart\Validation;

use Kommandhub\FlutterwaveV3SW\Checkout\Cart\Validation\TransactionCartValidator;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\FlutterwaveTransactionHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TransactionCartValidatorTest extends TestCase
{
    private TransactionCartValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TransactionCartValidator();
    }

    public function testValidateSkipsIfDifferentPaymentMethod(): void
    {
        $cart = $this->createMock(Cart::class);
        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier('SomeOtherHandler');

        $context->method('getPaymentMethod')->willReturn($paymentMethod);

        $this->validator->validate($cart, $errors, $context);
        $this->assertCount(0, $errors);
    }

    public function testValidateBlocksZeroValueCart(): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection([new LineItem('id', 'type')]));
        $cart->setPrice(new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = $this->createMock(PaymentMethodEntity::class);
        $paymentMethod->method('getHandlerIdentifier')->willReturn(FlutterwaveTransactionHandler::class);
        $paymentMethod->method('getTranslation')->with('name')->willReturn('Flutterwave');

        $context->method('getPaymentMethod')->willReturn($paymentMethod);

        $this->validator->validate($cart, $errors, $context);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(PaymentMethodBlockedError::class, $errors->first());
    }

    public function testValidateDoesNotBlockNonZeroValueCart(): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection([new LineItem('id', 'type')]));
        $cart->setPrice(new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier(FlutterwaveTransactionHandler::class);

        $context->method('getPaymentMethod')->willReturn($paymentMethod);

        $this->validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }

    public function testIsZeroValueCartWithEmptyCart(): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection());

        $this->assertFalse($this->validator->isZeroValueCart($cart));
    }

    public function testIsZeroValueCartWithItemsAndPositivePrice(): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection([new LineItem('id', 'type')]));
        $cart->setPrice(new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->assertFalse($this->validator->isZeroValueCart($cart));
    }

    public function testIsZeroValueCartWithItemsAndZeroPrice(): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection([new LineItem('id', 'type')]));
        $cart->setPrice(new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->assertTrue($this->validator->isZeroValueCart($cart));
    }
}
