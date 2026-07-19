<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Webhook\Controller;

use Kommandhub\FlutterwaveV3SW\Logging\ConfigurableLogger;
use Kommandhub\FlutterwaveV3SW\Webhook\Controller\WebhookController;
use Kommandhub\FlutterwaveV3SW\Webhook\Service\WebhookProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(WebhookController::class)]
class WebhookControllerTest extends TestCase
{
    private WebhookProcessor&MockObject $processor;
    private ConfigurableLogger&MockObject $logger;
    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(WebhookProcessor::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);
        $this->controller = new WebhookController($this->processor, $this->logger);
    }

    /**
     * Flutterwave treats any non-200 — including 204 and 3xx — as a failed
     * delivery and keeps retrying, so success must be exactly 200.
     */
    public function testHandledWebhookReturns200NotNoContent(): void
    {
        $response = $this->controller->execute(new Request(), Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Not Flutterwave: reject and never retry.
     */
    public function testInvalidSignatureReturns403AndLogs(): void
    {
        $this->processor->method('process')->willThrowException(new AccessDeniedHttpException('bad hash'));
        $this->logger->expects(static::once())->method('warning');

        $response = $this->controller->execute(new Request(), Context::createDefaultContext());

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * A redelivery would be byte-identical and fail identically, so retrying is
     * pointless: 400, not 500.
     */
    public function testMalformedPayloadReturns400AndLogs(): void
    {
        $this->processor->method('process')->willThrowException(new BadRequestHttpException('bad body'));
        $this->logger->expects(static::once())->method('warning');

        $response = $this->controller->execute(new Request(), Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Our fault, not theirs: 500 so Flutterwave retries the delivery.
     */
    public function testUnexpectedFailureReturns500SoFlutterwaveRetries(): void
    {
        $this->processor->method('process')->willThrowException(new \RuntimeException('db down'));
        $this->logger->expects(static::once())->method('error');

        $response = $this->controller->execute(new Request(), Context::createDefaultContext());

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }
}
