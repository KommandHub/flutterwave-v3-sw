<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Service;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Struct\PaymentPayload;
use Kommandhub\FlutterwaveV3SW\Setting\Service\Config;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;

/**
 * PayloadBuilder is responsible for constructing the payment payload for the Flutterwave API.
 * It maps Shopware's order and customer data to the format expected by Flutterwave V3.
 */
class PayloadBuilder
{
    public function __construct(
        private readonly Config $settingService
    ) {
    }

    /**
     * Builds the PaymentPayload object using the provided order transaction and payment transaction data.
     *
     * @param OrderTransactionEntity $orderTransaction The Shopware order transaction entity.
     * @param PaymentTransactionStruct $transaction The payment transaction struct.
     *
     * @return PaymentPayload The constructed Flutterwave payment payload.
     *
     * @throws \RuntimeException If required order, customer, or currency information is missing.
     */
    public function build(OrderTransactionEntity $orderTransaction, PaymentTransactionStruct $transaction): PaymentPayload
    {
        // 1. Retrieve the order from the transaction.
        $order = $orderTransaction->getOrder();

        if ($order === null) {
            throw new \RuntimeException('Order information is missing for the payment transaction.');
        }

        // 2. Extract customer details.
        $orderCustomer = $order->getOrderCustomer();

        if ($orderCustomer === null) {
            throw new \RuntimeException('Customer information is missing for the order.');
        }

        // 3. Get the order currency.
        $currency = $order->getCurrency();

        if ($currency === null) {
            throw new \RuntimeException('Currency information is missing for the order.');
        }

        // 4. Retrieve the return URL and sales channel ID.
        $returnUrl = $transaction->getReturnUrl();
        $salesChannelId = $order->getSalesChannelId();

        if (!is_string($returnUrl)) {
            throw new \RuntimeException('Return URL is missing for the payment transaction.');
        }

        // 5. Assemble the payload. The amount stays in major units: Flutterwave V3
        //    charges the value it is given, so scaling it here would overcharge.
        return new PaymentPayload(
            $orderTransaction->getAmount()->getTotalPrice(),
            $currency->getIsoCode(),
            $orderTransaction->getId(),
            $returnUrl,
            $orderCustomer->getEmail(),
            sprintf('%s %s', $orderCustomer->getFirstName(), $orderCustomer->getLastName()),
            null, // phonenumber is optional
            $this->settingService->getTitle($salesChannelId),
            $this->settingService->getLogo($salesChannelId),
            $this->settingService->getDescription($salesChannelId)
        );
    }
}
