<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Checkout\Payment\Service;

use Kommandhub\FlutterwaveSW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveSW\Checkout\Payment\Struct\FlutterwaveInitializationResponse;
use Kommandhub\FlutterwaveSW\Client\FlutterwaveClient;
use Kommandhub\FlutterwaveSW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveSW\Service\PayloadBuilder;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;

readonly class PaymentProcessor
{
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private FlutterwaveClient $flutterwave,
        private PayloadBuilder $payloadBuilder,
        private ConfigurableLogger $logger
    ) {
    }

    /**
     * Processes the initialization of a Flutterwave payment.
     *
     * @throws PaymentException
     */
    public function process(
        PaymentTransactionStruct $transaction,
        Context $context
    ): FlutterwaveInitializationResponse {
        $orderTransaction = $this->orderTransactionService->getOrderTransaction($transaction->getOrderTransactionId(), $context);
        $order = $orderTransaction->getOrder();
        $salesChannelId = $order?->getSalesChannelId();

        $this->logger->info('[Flutterwave] Pay started.', $this->logContext($salesChannelId, [
            'orderTransactionId' => $transaction->getOrderTransactionId(),
            'orderNumber' => $orderTransaction->getOrder()?->getOrderNumber(),
        ]));

        try {
            if ($salesChannelId === null) {
                throw new \RuntimeException('Sales channel ID is missing.');
            }

            $payload = $this->payloadBuilder->build($orderTransaction, $transaction);

            $response = $this->flutterwave->transactions()->initialize($payload->toArray(), $salesChannelId);

            $this->logger->debug('[Flutterwave] Initialize response received.', $this->logContext($salesChannelId, [
                'response' => $response,
            ]));

            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $link = $data['link'] ?? null;

            if (($response['status'] ?? null) !== 'success' || !is_string($link) || $link === '') {
                throw new \RuntimeException(
                    'Failed to initialize Flutterwave payment: ' . $this->describeError($response)
                );
            }

            return new FlutterwaveInitializationResponse($link);
        } catch (\Exception $e) {
            $this->logger->error('[Flutterwave] Pay failed.', $this->logContext($salesChannelId, [
                'orderTransactionId' => $orderTransaction->getId(),
                'exception' => $e,
            ]));

            throw PaymentException::asyncProcessInterrupted($orderTransaction->getId(), $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function describeError(array $response): string
    {
        $message = $response['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : 'Unknown error';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function logContext(?string $salesChannelId, array $context = []): array
    {
        return [ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId] + $context;
    }
}
