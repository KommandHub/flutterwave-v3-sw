<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Service;

use Kommandhub\Flutterwave\Payloads\CustomerPayload;
use Kommandhub\Flutterwave\Payloads\CustomizationsPayload;
use Kommandhub\Flutterwave\Payloads\PaymentPayload;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;

/**
 * PayloadBuilder is responsible for constructing the payment payload for the Flutterwave API.
 * It maps Shopware's order and customer data to the format expected by the Flutterwave SDK.
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

        // 5. Create the customer payload.
        $customerPayload = new CustomerPayload(
            $orderCustomer->getEmail(),
            null, // phonenumber is optional
            sprintf('%s %s', $orderCustomer->getFirstName(), $orderCustomer->getLastName())
        );

        // 6. Create the customizations payload based on plugin settings.
        $customizationsPayload = new CustomizationsPayload(
            $this->settingService->getTitle($salesChannelId),
            $this->settingService->getLogo($salesChannelId),
            $this->settingService->getDescription($salesChannelId)
        );

        // 7. Assemble the final PaymentPayload.
        return new PaymentPayload(
            $orderTransaction->getAmount()->getTotalPrice(),
            $currency->getIsoCode(),
            $orderTransaction->getId(),
            $returnUrl,
            $customerPayload,
            $customizationsPayload
        );
    }
}
