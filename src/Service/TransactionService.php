<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Service;

use Kommandhub\Flutterwave\Exceptions\FlutterwaveException;
use Kommandhub\Flutterwave\Flutterwave;
use Kommandhub\Flutterwave\Payloads\PaymentPayload;

/**
 * TransactionService serves as a wrapper for the Flutterwave SDK.
 * It handles the communication with the Flutterwave API for transaction initialization and verification.
 */
class TransactionService
{
    public function __construct(
        private readonly Config $settingService,
        private readonly TransactionFactory $transactionFactory
    ) {
    }

    /**
     * Initializes a transaction on the Flutterwave API.
     *
     * @param PaymentPayload $payload The payment payload constructed by the PayloadBuilder.
     * @param string $salesChannelId The sales channel ID for scoped configuration.
     * @return array The initialization response from Flutterwave.
     *
     * @throws FlutterwaveException If the Flutterwave SDK encounters an error.
     */
    public function initialize(PaymentPayload $payload, string $salesChannelId): array
    {
        $flutterwave = $this->getClient($salesChannelId);

        return $this->transactionFactory->create($flutterwave)->initialize($payload);
    }

    /**
     * Verifies a transaction status using the Flutterwave API.
     *
     * @param string $transactionId The unique transaction ID returned by Flutterwave.
     * @param string $salesChannelId The sales channel ID for scoped configuration.
     * @return array The verification response from Flutterwave.
     *
     * @throws FlutterwaveException If the Flutterwave SDK encounters an error.
     */
    public function verify(string $transactionId, string $salesChannelId): array
    {
        $flutterwave = $this->getClient($salesChannelId);

        return $this->transactionFactory->create($flutterwave)->verify($transactionId);
    }

    /**
     * Configures and returns a Flutterwave client instance.
     *
     * @param string $salesChannelId The sales channel ID for scoped configuration.
     * @return Flutterwave The configured Flutterwave client.
     *
     * @throws \RuntimeException If the API secret key is missing.
     */
    private function getClient(string $salesChannelId): Flutterwave
    {
        $secretKey = $this->settingService->getApiSecretKey($salesChannelId);

        if (empty($secretKey)) {
            throw new \RuntimeException('Flutterwave secret key is not configured.');
        }

        return new Flutterwave($secretKey);
    }
}
