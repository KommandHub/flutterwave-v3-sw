<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Service;

use Kommandhub\FlutterwaveV3SW\Service\Config;
use Kommandhub\FlutterwaveV3SW\Service\PayloadBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\System\Currency\CurrencyEntity;

class PayloadBuilderTest extends TestCase
{
    private Config $config;
    private PayloadBuilder $payloadBuilder;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->payloadBuilder = new PayloadBuilder($this->config);
    }

    public function testBuild(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('transaction-id');
        $orderTransaction->setAmount(
            new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $order = new OrderEntity();
        $order->setSalesChannelId('sales-channel-id');
        
        $currency = new CurrencyEntity();
        $currency->setIsoCode('NGN');
        $order->setCurrency($currency);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setEmail('test@example.com');
        $orderCustomer->setFirstName('John');
        $orderCustomer->setLastName('Doe');
        $order->setOrderCustomer($orderCustomer);

        $orderTransaction->setOrder($order);

        $paymentTransactionStruct = new PaymentTransactionStruct(
            'transaction-id',
            'http://return.url'
        );

        $this->config->expects($this->once())
            ->method('getTitle')
            ->with('sales-channel-id')
            ->willReturn('Test Title');
        $this->config->expects($this->once())
            ->method('getLogo')
            ->with('sales-channel-id')
            ->willReturn('http://logo.url');
        $this->config->expects($this->once())
            ->method('getDescription')
            ->with('sales-channel-id')
            ->willReturn('Test Description');

        $payload = $this->payloadBuilder->build($orderTransaction, $paymentTransactionStruct);
        $payloadArray = $payload->toArray();

        $this->assertEquals(100.0, $payloadArray['amount']);
        $this->assertEquals('NGN', $payloadArray['currency']);
        $this->assertEquals('transaction-id', $payloadArray['tx_ref']);
        $this->assertEquals('http://return.url', $payloadArray['redirect_url']);
        $this->assertEquals('test@example.com', $payloadArray['customer']['email']);
        $this->assertEquals('John Doe', $payloadArray['customer']['name']);
        $this->assertEquals('Test Title', $payloadArray['customizations']['title']);
        $this->assertEquals('http://logo.url', $payloadArray['customizations']['logo']);
        $this->assertEquals('Test Description', $payloadArray['customizations']['description']);
    }

    public function testBuildThrowsExceptionWhenOrderIsMissing(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $paymentTransactionStruct = $this->createMock(PaymentTransactionStruct::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Order information is missing for the payment transaction.');

        $this->payloadBuilder->build($orderTransaction, $paymentTransactionStruct);
    }

    public function testBuildThrowsExceptionWhenCustomerIsMissing(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $order = new OrderEntity();
        $orderTransaction->setOrder($order);
        $paymentTransactionStruct = $this->createMock(PaymentTransactionStruct::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer information is missing for the order.');

        $this->payloadBuilder->build($orderTransaction, $paymentTransactionStruct);
    }

    public function testBuildThrowsExceptionWhenCurrencyIsMissing(): void
    {
        $orderTransaction = new OrderTransactionEntity();
        $order = new OrderEntity();
        $orderCustomer = new OrderCustomerEntity();
        $order->setOrderCustomer($orderCustomer);
        $orderTransaction->setOrder($order);
        $paymentTransactionStruct = $this->createMock(PaymentTransactionStruct::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Currency information is missing for the order.');

        $this->payloadBuilder->build($orderTransaction, $paymentTransactionStruct);
    }
}
