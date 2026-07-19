<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Webhook\Subscriber;

use Kommandhub\FlutterwaveV3SW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\FlutterwaveV3SW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveV3SW\Service\OrderTransactionService;
use Kommandhub\FlutterwaveV3SW\Webhook\Event\ChargeCompletedEvent;
use Kommandhub\FlutterwaveV3SW\Webhook\Service\WebhookDeduplicator;
use Kommandhub\FlutterwaveV3SW\Webhook\Subscriber\ChargeCompletedSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ChargeCompletedSubscriber::class)]
#[UsesClass(ChargeCompletedEvent::class)]
#[UsesClass(WebhookDeduplicator::class)]
class ChargeCompletedSubscriberTest extends TestCase
{
    private OrderTransactionService&MockObject $orderTransactionService;
    private FinalizeProcessor&MockObject $finalizeProcessor;
    private WebhookDeduplicator&MockObject $deduplicator;
    private ConfigurableLogger&MockObject $logger;
    private ChargeCompletedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->finalizeProcessor = $this->createMock(FinalizeProcessor::class);
        $this->deduplicator = $this->createMock(WebhookDeduplicator::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);

        $this->subscriber = new ChargeCompletedSubscriber(
            $this->orderTransactionService,
            $this->finalizeProcessor,
            $this->deduplicator,
            $this->logger
        );
    }

    private function transaction(?string $state = 'open'): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId(self::ORDER_TRANSACTION_ID);
        $transaction->setCustomFields([]);

        if ($state !== null) {
            $stateEntity = new StateMachineStateEntity();
            $stateEntity->setTechnicalName($state);
            $transaction->setStateMachineState($stateEntity);
        }

        return $transaction;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function event(array $data): ChargeCompletedEvent
    {
        return new ChargeCompletedEvent($data, Context::createDefaultContext());
    }

    /**
     * tx_ref is a Shopware order transaction id, so the fixture must be a real
     * UUID — the subscriber rejects anything else as another integration's.
     */
    private const ORDER_TRANSACTION_ID = '0199aaaabbbbccccddddeeeeffff0001';

    private function validEvent(): ChargeCompletedEvent
    {
        return $this->event(['id' => 12345, 'tx_ref' => self::ORDER_TRANSACTION_ID, 'status' => 'successful']);
    }

    /**
     * The payload only says which transaction to look at; FinalizeProcessor
     * re-verifies against the API. Critically, the payload's own status must NOT
     * be forwarded, or a forged body could steer the outcome.
     */
    public function testSettlesPaymentByReVerifyingAndDoesNotForwardPayloadStatus(): void
    {
        $this->orderTransactionService->method('getOrderTransaction')->willReturn($this->transaction());

        $this->finalizeProcessor->expects(static::once())
            ->method('process')
            ->with(static::callback(static function (Request $request): bool {
                return $request->query->get('transaction_id') === '12345'
                    && $request->query->get('status') === null;
            }));

        $this->deduplicator->expects(static::once())->method('markProcessed');

        ($this->subscriber)($this->validEvent());
    }

    public function testDuplicateDeliveryIsIgnored(): void
    {
        $this->orderTransactionService->method('getOrderTransaction')->willReturn($this->transaction());
        $this->deduplicator->method('isProcessed')->willReturn(true);

        $this->finalizeProcessor->expects(static::never())->method('process');

        ($this->subscriber)($this->validEvent());
    }

    /**
     * The redirect flow may have settled it already; the webhook must not
     * re-settle, but should still record the delivery so retries stop.
     */
    public function testAlreadyPaidTransactionIsNotSettledAgainButIsMarked(): void
    {
        $this->orderTransactionService->method('getOrderTransaction')
            ->willReturn($this->transaction(OrderTransactionStates::STATE_PAID));

        $this->finalizeProcessor->expects(static::never())->method('process');
        $this->deduplicator->expects(static::once())->method('markProcessed');

        ($this->subscriber)($this->validEvent());
    }

    /**
     * A declined payment is a real outcome, not a delivery failure: swallow so
     * Flutterwave stops retrying an event that can never succeed.
     */
    public function testFailedSettlementIsSwallowedAndStillMarked(): void
    {
        $this->orderTransactionService->method('getOrderTransaction')->willReturn($this->transaction());
        $this->finalizeProcessor->method('process')->willThrowException(new \RuntimeException('declined'));

        $this->deduplicator->expects(static::once())->method('markProcessed');

        ($this->subscriber)($this->validEvent());
    }

    public function testUnknownReferenceIsAcknowledgedWithoutAction(): void
    {
        $this->orderTransactionService->method('getOrderTransaction')
            ->willThrowException(new \InvalidArgumentException());

        $this->finalizeProcessor->expects(static::never())->method('process');

        ($this->subscriber)($this->validEvent());
    }

    public function testPayloadMissingIdentifiersIsIgnored(): void
    {
        $this->orderTransactionService->expects(static::never())->method('getOrderTransaction');

        ($this->subscriber)($this->event(['status' => 'successful']));
    }

    /**
     * Regression: Flutterwave delivers every charge on the account, including
     * other integrations' whose tx_ref is not a Shopware id. Handing a non-UUID
     * to the DAL throws, which surfaced as a 500 and made Flutterwave retry a
     * delivery that could never succeed. It must be ignored before the lookup.
     */
    public function testNonUuidReferenceIsIgnoredBeforeTouchingTheDatabase(): void
    {
        $this->orderTransactionService->expects(static::never())->method('getOrderTransaction');
        $this->finalizeProcessor->expects(static::never())->method('process');

        ($this->subscriber)($this->event(['id' => 12345, 'tx_ref' => 'some-other-integration-ref']));
    }
}
