<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * OrderTransactionService handles common operations for retrieving and updating order transactions.
 * It ensures the required associations are loaded for payment processing.
 */
class OrderTransactionService
{
    public function __construct(private readonly EntityRepository $orderTransactionRepository)
    {
    }

    /**
     * Updates an order transaction in the database.
     *
     * @param array $payload The update payload.
     * @param Context $context The Shopware context.
     */
    public function update(array $payload, Context $context): void
    {
        $this->orderTransactionRepository->update($payload, $context);
    }

    /**
     * Retrieves an order transaction with necessary associations for Flutterwave.
     *
     * @param string $transactionId The transaction ID.
     * @param Context $context The Shopware context.
     *
     * @return OrderTransactionEntity The order transaction.
     *
     * @throws \InvalidArgumentException If the transaction is not found.
     */
    public function getOrderTransaction(string $transactionId, Context $context): OrderTransactionEntity
    {
        $criteria = $this->getCriteria([$transactionId]);
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw new \InvalidArgumentException(sprintf(
                'Order transaction with id %s not found',
                $transactionId
            ));
        }

        return $orderTransaction;
    }

    /**
     * Builds the criteria with necessary associations.
     *
     * @param array $ids Optional transaction IDs.
     *
     * @return Criteria The search criteria.
     */
    private function getCriteria(array $ids = []): Criteria
    {
        $criteria = empty($ids) ? new Criteria() : new Criteria($ids);
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.orderCustomer.salutation');

        return $criteria;
    }
}
