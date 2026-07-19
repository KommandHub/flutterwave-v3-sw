<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Webhook\Service;

use Kommandhub\FlutterwaveV3SW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveV3SW\Webhook\Event\ChargeCompletedEvent;
use Kommandhub\FlutterwaveV3SW\Webhook\Service\WebhookEventFactory;
use Kommandhub\FlutterwaveV3SW\Webhook\Service\WebhookProcessor;
use Kommandhub\FlutterwaveV3SW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(WebhookProcessor::class)]
#[UsesClass(WebhookEventFactory::class)]
#[UsesClass(ChargeCompletedEvent::class)]
class WebhookProcessorTest extends TestCase
{
    private WebhookSignatureValidator&MockObject $signatureValidator;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ConfigurableLogger&MockObject $logger;
    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        $this->signatureValidator = $this->createMock(WebhookSignatureValidator::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);

        $this->processor = new WebhookProcessor(
            $this->signatureValidator,
            new WebhookEventFactory(),
            $this->eventDispatcher,
            $this->logger
        );
    }

    private function request(string $body): Request
    {
        return new Request([], [], [], [], [], [], $body);
    }

    public function testValidChargeCompletedIsDispatched(): void
    {
        $body = json_encode(['event' => 'charge.completed', 'data' => ['id' => 12345, 'tx_ref' => 'ref']]);

        $this->eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ChargeCompletedEvent::class));

        $this->processor->process($this->request((string)$body), Context::createDefaultContext());
    }

    /**
     * The signature gate runs before anything else touches the body.
     */
    public function testInvalidSignatureAbortsBeforeDispatch(): void
    {
        $this->signatureValidator->method('validate')->willThrowException(new AccessDeniedHttpException('nope'));
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->expectException(AccessDeniedHttpException::class);

        $this->processor->process($this->request('{}'), Context::createDefaultContext());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedPayloadProvider(): array
    {
        return [
            'not json' => ['<html>oops</html>'],
            'json scalar' => ['"a string"'],
            'no event name' => ['{"data":{}}'],
            'empty event name' => ['{"event":"","data":{}}'],
            'no data object' => ['{"event":"charge.completed"}'],
            'data not an object' => ['{"event":"charge.completed","data":"nope"}'],
        ];
    }

    #[DataProvider('malformedPayloadProvider')]
    public function testMalformedPayloadIsRejected(string $body): void
    {
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->expectException(BadRequestHttpException::class);

        $this->processor->process($this->request($body), Context::createDefaultContext());
    }

    /**
     * Flutterwave sends account-wide events this plugin has no business acting
     * on. They are acknowledged, not treated as errors, so retries stop.
     */
    public function testUnhandledEventIsAcknowledgedWithoutDispatch(): void
    {
        $body = json_encode(['event' => 'transfer.completed', 'data' => ['id' => 1]]);

        // Reaching the end without a BadRequestHttpException is the assertion:
        // an unknown event must be swallowed, not rejected.
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->processor->process($this->request((string)$body), Context::createDefaultContext());
    }
}
