<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment;

use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveV3SW\Service\PayloadBuilder;
use Kommandhub\FlutterwaveV3SW\Service\TransactionService;
use Kommandhub\FlutterwaveV3SW\Service\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * FlutterwaveTransactionHandler manages the payment lifecycle for Flutterwave in Shopware 6.
 * It handles the initial redirect to Flutterwave and the verification of the payment upon return.
 */
class FlutterwaveTransactionHandler extends AbstractPaymentHandler
{
    /**
     * @param OrderTransactionService $orderTransactionService Service to handle order transaction operations.
     * @param TransactionService $transactionService Service to interact with the Flutterwave API.
     * @param PayloadBuilder $payloadBuilder Service to build the payment payload.
     * @param OrderTransactionStateHandler $transactionStateHandler Shopware service to manage transaction states.
     * @param Config $config Plugin configuration service.
     * @param LoggerInterface $logger Logger for debugging and error tracking.
     */
    public function __construct(
        private readonly OrderTransactionService $orderTransactionService,
        private readonly TransactionService $transactionService,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Initiates the payment process by building the payload and redirecting the customer to Flutterwave.
     *
     * @param Request $request The current HTTP request.
     * @param PaymentTransactionStruct $transaction The payment transaction data.
     * @param Context $context The Shopware context.
     * @param Struct|null $validateStruct Optional validation data.
     *
     * @return RedirectResponse|null A redirect to the Flutterwave checkout page.
     *
     * @throws PaymentException If the payment initiation fails.
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse {
        // 1. Retrieve the order transaction with necessary associations.
        $orderTransaction = $this->orderTransactionService->getOrderTransaction($transaction->getOrderTransactionId(), $context);
        $salesChannelId = $orderTransaction->getOrder()?->getSalesChannelId();

        // 2. Log payment start if debugging is enabled.
        if ($this->config->isDebugEnabled($salesChannelId)) {
            $this->logger->info('Flutterwave pay started', [
                'orderTransactionId' => $transaction->getOrderTransactionId(),
                'orderNumber' => $orderTransaction->getOrder()?->getOrderNumber(),
            ]);
        }

        try {
            // 3. Build the payment payload for Flutterwave.
            $payload = $this->payloadBuilder->build($orderTransaction, $transaction);

            if ($this->config->isDebugEnabled($salesChannelId)) {
                $this->logger->info('Flutterwave payload built', [
                    'payload' => $payload->toArray(),
                ]);
            }

            if ($salesChannelId === null) {
                throw new \RuntimeException('Sales channel ID is missing.'); // @codeCoverageIgnore
            }

            // 4. Initialize the transaction on Flutterwave.
            $response = $this->transactionService->initialize($payload, $salesChannelId);

            if ($this->config->isDebugEnabled($salesChannelId)) {
                $this->logger->info('Flutterwave initialize response', [
                    'response' => $response,
                ]);
            }

            // 5. Validate the response from Flutterwave.
            if ($response['status'] !== 'success' || !isset($response['data']['link'])) {
                throw new \RuntimeException('Failed to initialize Flutterwave payment: ' . ($response['message'] ?? 'Unknown error')); // @codeCoverageIgnore
            }

            // 6. Redirect the customer to the Flutterwave payment page.
            return new RedirectResponse($response['data']['link']);
        } catch (\Exception $e) {
            // Log the error if debugging is enabled.
            if ($this->config->isDebugEnabled($salesChannelId)) {
                $this->logger->error('Flutterwave pay error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            // Throw a Shopware-specific payment exception.
            throw PaymentException::asyncProcessInterrupted($orderTransaction->getId(), $e->getMessage());
        }
    }

    /**
     * Finalizes the payment process after the customer is redirected back from Flutterwave.
     * Verifies the transaction status and updates the Shopware order transaction state.
     *
     * @param Request $request The current HTTP request.
     * @param PaymentTransactionStruct $transaction The payment transaction data.
     * @param Context $context The Shopware context.
     *
     * @throws PaymentException If the payment finalization fails.
     */
    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        // 1. Retrieve the order transaction.
        $orderTransaction = $this->orderTransactionService->getOrderTransaction($transaction->getOrderTransactionId(), $context);
        $salesChannelId = $orderTransaction->getOrder()?->getSalesChannelId();

        // 2. Log finalization start if debugging is enabled.
        if ($this->config->isDebugEnabled($salesChannelId)) {
            $this->logger->info('Flutterwave finalize started', [
                'orderTransactionId' => $transaction->getOrderTransactionId(),
                'queryParams' => $request->query->all(),
            ]);
        }

        if ($salesChannelId === null) {
            throw PaymentException::asyncFinalizeInterrupted($orderTransaction->getId(), 'Sales channel ID is missing.'); // @codeCoverageIgnore
        }

        // 3. Check for cancellation or missing transaction ID.
        $status = $request->query->get('status');
        $transactionId = $request->query->get('transaction_id');

        if ($status === 'cancelled') {
            if ($this->config->isDebugEnabled($salesChannelId)) {
                $this->logger->info('Flutterwave payment cancelled by customer'); // @codeCoverageIgnore
            }
            $this->transactionStateHandler->cancel($orderTransaction->getId(), $context);
            throw PaymentException::customerCanceled($orderTransaction->getId(), 'Customer canceled the payment on Flutterwave.');
        }

        if ($transactionId === null) {
            throw PaymentException::asyncFinalizeInterrupted($orderTransaction->getId(), 'Flutterwave transaction ID is missing.');
        }

        try {
            // 4. Verify the transaction with Flutterwave.
            $response = $this->transactionService->verify((string)$transactionId, $salesChannelId);

            if ($this->config->isDebugEnabled($salesChannelId)) {
                $this->logger->info('Flutterwave verify response', [
                    'response' => $response,
                ]);
            }

            if ($response['status'] !== 'success') {
                throw new \RuntimeException('Flutterwave verification failed: ' . ($response['message'] ?? 'Unknown error'));
            }

            $data = $response['data'];

            // 5. Verify that amount and currency match the order to prevent tampering.
            $expectedAmount = $orderTransaction->getAmount()->getTotalPrice();
            $expectedCurrency = $orderTransaction->getOrder()?->getCurrency()?->getIsoCode();

            if (abs($data['amount'] - $expectedAmount) > 0.01 || $data['currency'] !== $expectedCurrency) {
                if ($this->config->isDebugEnabled($salesChannelId)) {
                    // @codeCoverageIgnoreStart
                    $this->logger->error('Flutterwave amount or currency mismatch', [
                        'expectedAmount' => $expectedAmount,
                        'receivedAmount' => $data['amount'],
                        'expectedCurrency' => $expectedCurrency,
                        'receivedCurrency' => $data['currency'],
                    ]);
                    // @codeCoverageIgnoreEnd
                }
                throw new \RuntimeException('Flutterwave verification failed: Amount or currency mismatch.');
            }

            // 6. Update the order transaction state based on the payment status.
            if ($data['status'] === 'successful') {
                if ($this->config->isDebugEnabled($salesChannelId)) {
                    $this->logger->info('Flutterwave payment successful');
                }
                $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
            } elseif ($data['status'] === 'failed') {
                if ($this->config->isDebugEnabled($salesChannelId)) {
                    $this->logger->info('Flutterwave payment failed');
                }
                $this->transactionStateHandler->fail($orderTransaction->getId(), $context);
                throw new \RuntimeException('Flutterwave payment failed.');
            } else {
                if ($this->config->isDebugEnabled($salesChannelId)) {
                    $this->logger->info('Flutterwave payment status: ' . $data['status']); // @codeCoverageIgnore
                }
                $this->transactionStateHandler->reopen($orderTransaction->getId(), $context);
            }
        } catch (\Exception $e) {
            // Log errors during finalization.
            if ($this->config->isDebugEnabled($salesChannelId)) {
                $this->logger->error('Flutterwave finalize error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            throw PaymentException::asyncFinalizeInterrupted($orderTransaction->getId(), $e->getMessage());
        }
    }
}