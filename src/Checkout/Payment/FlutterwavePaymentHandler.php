<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Checkout\Payment;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\PaymentProcessor;
use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\RefundProcessor;
use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * FlutterwavePaymentHandler manages the payment lifecycle for Flutterwave in Shopware 6.
 * It handles the initial redirect to Flutterwave and the verification of the payment upon return.
 */
#[AutoconfigureTag('shopware.payment_handler')]
class FlutterwavePaymentHandler extends AbstractPaymentHandler
{
    public function __construct(
        private readonly OrderTransactionService $orderTransactionService,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly FinalizeProcessor $finalizeProcessor,
        private readonly RefundProcessor $refundProcessor,
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
        $response = $this->paymentProcessor->process($transaction, $context);

        return new RedirectResponse($response->getLink());
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
        $this->finalizeProcessor->process($request, $transaction, $context);
    }

    /**
     * @param RefundPaymentTransactionStruct $transaction
     * @param Context $context
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function refund(RefundPaymentTransactionStruct $transaction, Context $context): void
    {
        $this->refundProcessor->process($transaction, $context);
    }

    /**
     * Retrieves the order transaction entity.
     *
     * @throws PaymentException
     */
    public function getOrderTransaction(string $transactionId, Context $context): OrderTransactionEntity
    {
        return $this->orderTransactionService->getOrderTransaction($transactionId, $context);
    }
}
