<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Tests\Unit\Webhook\Service;

use Kommandhub\FlutterwaveV3SW\Setting\Service\Config;
use Kommandhub\FlutterwaveV3SW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(WebhookSignatureValidator::class)]
class WebhookSignatureValidatorTest extends TestCase
{
    private Config&MockObject $config;
    private WebhookSignatureValidator $validator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->validator = new WebhookSignatureValidator($this->config);
    }

    private function request(?string $signature): Request
    {
        $request = new Request();

        if ($signature !== null) {
            $request->headers->set(WebhookSignatureValidator::SIGNATURE_HEADER, $signature);
        }

        return $request;
    }

    public function testMatchingHashPasses(): void
    {
        $this->config->method('getSecretHash')->willReturn('s3cret');

        $this->expectNotToPerformAssertions();
        $this->validator->validate($this->request('s3cret'));
    }

    public function testMismatchedHashIsRejected(): void
    {
        $this->config->method('getSecretHash')->willReturn('s3cret');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid Flutterwave verif-hash');

        $this->validator->validate($this->request('wrong'));
    }

    public function testMissingHeaderIsRejected(): void
    {
        $this->config->method('getSecretHash')->willReturn('s3cret');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Missing Flutterwave verif-hash');

        $this->validator->validate($this->request(null));
    }

    public function testEmptyHeaderIsRejected(): void
    {
        $this->config->method('getSecretHash')->willReturn('s3cret');

        $this->expectException(AccessDeniedHttpException::class);
        $this->validator->validate($this->request(''));
    }

    /**
     * An unconfigured plugin must not accept every caller: with no hash there is
     * nothing to prove the sender is Flutterwave, so it fails closed.
     */
    public function testUnconfiguredSecretHashFailsClosedEvenWhenAHeaderIsSent(): void
    {
        $this->config->method('getSecretHash')->willReturn('');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('not configured');

        $this->validator->validate($this->request('anything'));
    }

    public function testSalesChannelIdIsForwardedToConfig(): void
    {
        $this->config->expects(static::once())
            ->method('getSecretHash')
            ->with('sales-channel-id')
            ->willReturn('s3cret');

        $this->validator->validate($this->request('s3cret'), 'sales-channel-id');
    }
}
