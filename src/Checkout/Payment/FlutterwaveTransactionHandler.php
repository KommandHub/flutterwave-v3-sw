<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment;

use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveV3SW\Service\PayloadBuilder;
use Kommandhub\FlutterwaveV3SW\Service\TransactionService;
use Kommandhub\FlutterwaveV3SW\Service\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
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
    public const FIELD_REFERENCE = 'flutterwave_reference';
    public const FIELD_TRANSACTION_ID = 'flutterwave_transaction_id';
    public const FIELD_PAYMENT_TYPE = 'flutterwave_payment_type';
    public const FIELD_TRANSACTION_FEE = 'flutterwave_transaction_fee';
    public const FIELD_AMOUNT_CHARGED = 'flutterwave_amount_charged';
    public const FIELD_AMOUNT_SETTLED = 'flutterwave_amount_settled';
    public const FIELD_CURRENCY = 'flutterwave_currency';
    public const FIELD_VERIFIED_AT = 'flutterwave_verified_at';
    public const FIELD_CUSTOMER = 'flutterwave_customer';

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
        $this->logInfo('Flutterwave pay started', [
            'orderTransactionId' => $transaction->getOrderTransactionId(),
            'orderNumber' => $orderTransaction->getOrder()?->getOrderNumber(),
        ], $salesChannelId);

        try {
            // 3. Build the payment payload for Flutterwave.
            $payload = $this->payloadBuilder->build($orderTransaction, $transaction);

            $this->logInfo('Flutterwave payload built', [
                'payload' => $payload->toArray(),
            ], $salesChannelId);

            if ($salesChannelId === null) {
                throw new \RuntimeException('Sales channel ID is missing.'); // @codeCoverageIgnore
            }

            // 4. Initialize the transaction on Flutterwave.
            $response = $this->transactionService->initialize($payload, $salesChannelId);

            $this->logInfo('Flutterwave initialize response', [
                'response' => $response,
            ], $salesChannelId);

            // 5. Validate the response from Flutterwave.
            if ($response['status'] !== 'success' || !isset($response['data']['link'])) {
                throw new \RuntimeException('Failed to initialize Flutterwave payment: ' . ($response['message'] ?? 'Unknown error')); // @codeCoverageIgnore
            }

            // 6. Redirect the customer to the Flutterwave payment page.
            return new RedirectResponse($response['data']['link']);
        } catch (\Exception $e) {
            // Log the error if debugging is enabled.
            $this->logError('Flutterwave pay error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $salesChannelId);

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
        $this->logInfo('Flutterwave finalize started', [
            'orderTransactionId' => $transaction->getOrderTransactionId(),
            'queryParams' => $request->query->all(),
        ], $salesChannelId);

        if ($salesChannelId === null) {
            throw PaymentException::asyncFinalizeInterrupted($orderTransaction->getId(), 'Sales channel ID is missing.'); // @codeCoverageIgnore
        }

        // 3. Check for cancellation or missing transaction ID.
        $status = $request->query->get('status');
        $transactionId = $request->query->get('transaction_id');

        if ($status === 'cancelled') {
            $this->logInfo('Flutterwave payment cancelled by customer', [], $salesChannelId);
            $this->transactionStateHandler->cancel($orderTransaction->getId(), $context);
            throw PaymentException::customerCanceled($orderTransaction->getId(), 'Customer canceled the payment on Flutterwave.');
        }

        if ($transactionId === null) {
            throw PaymentException::asyncFinalizeInterrupted($orderTransaction->getId(), 'Flutterwave transaction ID is missing.');
        }

        try {
            // 4. Verify the transaction with Flutterwave.
            $response = $this->transactionService->verify((string)$transactionId, $salesChannelId);

            $this->logInfo('Flutterwave verify response', [
                'response' => $response,
            ], $salesChannelId);

            if ($response['status'] !== 'success') {
                throw new \RuntimeException('Flutterwave verification failed: ' . ($response['message'] ?? 'Unknown error'));
            }

            $data = $response['data'];

            // 5. Verify that amount and currency match the order to prevent tampering.
            $this->validateTransactionData($orderTransaction, $data, $salesChannelId);

            // 6. Store transaction details in custom fields.
            $this->saveTransactionData($orderTransaction, $data, (string)$transactionId, $context);

            // 7. Update the order transaction state based on the payment status.
            $this->updateTransactionState($orderTransaction, $data['status'] ?? '', $context, $salesChannelId);
        } catch (\Exception $e) {
            // Log errors during finalization.
            $this->logError('Flutterwave finalize error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $salesChannelId);

            throw PaymentException::asyncFinalizeInterrupted($orderTransaction->getId(), $e->getMessage());
        }
    }

    /**
     * Validates that the amount and currency from Flutterwave match the order.
     *
     * @param OrderTransactionEntity $orderTransaction The Shopware order transaction.
     * @param array<string, mixed> $data The transaction data from Flutterwave.
     * @param string|null $salesChannelId The sales channel ID for logging.
     *
     * @throws \RuntimeException If the amount or currency do not match.
     */
    private function validateTransactionData(OrderTransactionEntity $orderTransaction, array $data, ?string $salesChannelId): void
    {
        $expectedAmount = $orderTransaction->getAmount()->getTotalPrice();
        $expectedCurrency = $orderTransaction->getOrder()?->getCurrency()?->getIsoCode();

        $actualAmount = 0.0;
        if (isset($data['amount']) && is_numeric($data['amount'])) {
            $actualAmount = (float)$data['amount'];
        }
        $actualCurrency = isset($data['currency']) && is_string($data['currency']) ? $data['currency'] : null;

        if (abs($actualAmount - $expectedAmount) > 0.01 || $actualCurrency !== $expectedCurrency) {
            $this->logError('Flutterwave amount or currency mismatch', [
                'expectedAmount' => $expectedAmount,
                'receivedAmount' => $actualAmount,
                'expectedCurrency' => $expectedCurrency,
                'receivedCurrency' => $actualCurrency,
            ], $salesChannelId);

            throw new \RuntimeException('Flutterwave verification failed: Amount or currency mismatch.');
        }
    }

    /**
     * Saves the Flutterwave transaction metadata in the order transaction's custom fields.
     *
     * @param OrderTransactionEntity $orderTransaction The Shopware order transaction.
     * @param array<string, mixed> $data The transaction data from Flutterwave.
     * @param string $transactionId The transaction ID.
     * @param Context $context The Shopware context.
     */
    private function saveTransactionData(OrderTransactionEntity $orderTransaction, array $data, string $transactionId, Context $context): void
    {
        $customFields = $orderTransaction->getCustomFields() ?? [];
        $customFields = array_merge($customFields, [
            self::FIELD_REFERENCE => $data['tx_ref'] ?? null,
            self::FIELD_TRANSACTION_ID => (isset($data['id']) && (is_string($data['id']) || is_numeric($data['id']))) ? (string)$data['id'] : $transactionId,
            self::FIELD_PAYMENT_TYPE => $data['payment_type'] ?? null,
            self::FIELD_TRANSACTION_FEE => $data['app_fee'] ?? null,
            self::FIELD_AMOUNT_CHARGED => $data['amount'] ?? ($data['charged_amount'] ?? null),
            self::FIELD_AMOUNT_SETTLED => $data['amount_settled'] ?? null,
            self::FIELD_CURRENCY => $data['currency'] ?? null,
            self::FIELD_VERIFIED_AT => (new \DateTime())->format(\DateTimeInterface::ATOM),
            self::FIELD_CUSTOMER => $data['customer'] ?? null,
        ]);

        $this->orderTransactionService->update([
            [
                'id' => $orderTransaction->getId(),
                'customFields' => $customFields,
            ],
        ], $context);
    }

    /**
     * Updates the Shopware order transaction state based on the Flutterwave payment status.
     *
     * @param OrderTransactionEntity $orderTransaction The Shopware order transaction.
     * @param string $status The payment status from Flutterwave.
     * @param Context $context The Shopware context.
     * @param string|null $salesChannelId The sales channel ID for logging.
     *
     * @throws \RuntimeException If the payment failed.
     */
    private function updateTransactionState(OrderTransactionEntity $orderTransaction, string $status, Context $context, ?string $salesChannelId): void
    {
        if ($status === 'successful') {
            $this->logInfo('Flutterwave payment successful', [], $salesChannelId);
            $this->transactionStateHandler->paid($orderTransaction->getId(), $context);
        } elseif ($status === 'failed') {
            $this->logInfo('Flutterwave payment failed', [], $salesChannelId);
            $this->transactionStateHandler->fail($orderTransaction->getId(), $context);
            throw new \RuntimeException('Flutterwave payment failed.');
        } else {
            $this->logInfo('Flutterwave payment status: ' . $status, [], $salesChannelId); // @codeCoverageIgnore
            $this->transactionStateHandler->reopen($orderTransaction->getId(), $context);
        }
    }

    /**
     * Logs informational messages if debugging is enabled.
     *
     * @param string $message The log message.
     * @param array<string, mixed> $context The log context.
     * @param string|null $salesChannelId The sales channel ID.
     */
    private function logInfo(string $message, array $context = [], ?string $salesChannelId = null): void
    {
        if ($this->config->isDebugEnabled($salesChannelId)) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Logs error messages if debugging is enabled.
     *
     * @param string $message The log message.
     * @param array<string, mixed> $context The log context.
     * @param string|null $salesChannelId The sales channel ID.
     */
    private function logError(string $message, array $context = [], ?string $salesChannelId = null): void
    {
        if ($this->config->isDebugEnabled($salesChannelId)) {
            $this->logger->error($message, $context);
        }
    }
}
