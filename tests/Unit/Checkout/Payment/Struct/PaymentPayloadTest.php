<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Tests\Unit\Checkout\Payment\Struct;

use Kommandhub\FlutterwaveSW\Checkout\Payment\Struct\PaymentPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentPayload::class)]
class PaymentPayloadTest extends TestCase
{
    private function createFullPayload(): PaymentPayload
    {
        return new PaymentPayload(
            100.5,
            'NGN',
            'tx-ref-123',
            'https://shop.example/return',
            'customer@example.com',
            'Ada Lovelace',
            '08012345678',
            'My Shop',
            'https://shop.example/logo.png',
            'Pay securely'
        );
    }

    public function testGetters(): void
    {
        $payload = $this->createFullPayload();

        static::assertSame('tx-ref-123', $payload->getTxRef());
        static::assertSame(100.5, $payload->getAmount());
        static::assertSame('NGN', $payload->getCurrency());
    }

    public function testToArrayProducesTheFlutterwaveWireShape(): void
    {
        static::assertSame([
            'amount' => 100.5,
            'currency' => 'NGN',
            'tx_ref' => 'tx-ref-123',
            'redirect_url' => 'https://shop.example/return',
            'customer' => [
                'email' => 'customer@example.com',
                'name' => 'Ada Lovelace',
                'phonenumber' => '08012345678',
            ],
            'customizations' => [
                'title' => 'My Shop',
                'logo' => 'https://shop.example/logo.png',
                'description' => 'Pay securely',
            ],
        ], $this->createFullPayload()->toArray());
    }

    /**
     * Flutterwave rejects some nulls outright rather than ignoring them, so
     * unset optional fields are dropped instead of sent as null.
     */
    public function testNullOptionalsAreOmittedRatherThanSentAsNull(): void
    {
        $payload = new PaymentPayload(
            100.0,
            'NGN',
            'tx-ref-123',
            'https://shop.example/return',
            'customer@example.com'
        );

        $array = $payload->toArray();

        static::assertSame(['email' => 'customer@example.com'], $array['customer']);
        static::assertSame([], $array['customizations']);
    }

    /**
     * The logo comes from SystemConfigService::getString(), which returns '' —
     * not null — when unset. An empty customization must be omitted from the
     * wire payload, not sent as "logo": "".
     */
    public function testEmptyCustomizationsAreOmittedRatherThanSentAsEmptyString(): void
    {
        $payload = new PaymentPayload(
            100.0,
            'NGN',
            'tx-ref-123',
            'https://shop.example/return',
            'customer@example.com',
            'Ada Lovelace',
            null,
            'My Shop',
            '',
            'Pay securely'
        );

        static::assertSame(
            ['title' => 'My Shop', 'description' => 'Pay securely'],
            $payload->toArray()['customizations']
        );
    }

    public function testPartialOptionalsKeepOnlyWhatWasProvided(): void
    {
        $payload = new PaymentPayload(
            100.0,
            'NGN',
            'tx-ref-123',
            'https://shop.example/return',
            'customer@example.com',
            'Ada Lovelace',
            null,
            'My Shop'
        );

        $array = $payload->toArray();

        static::assertSame(['email' => 'customer@example.com', 'name' => 'Ada Lovelace'], $array['customer']);
        static::assertSame(['title' => 'My Shop'], $array['customizations']);
    }

    /**
     * The regression this refactor exists to prevent. Flutterwave V3 charges the
     * amount it is given, so ₦100.50 must go out as 100.5 — not as the 10050
     * that Paystack's minor-unit convention would produce.
     *
     * @see https://developer.flutterwave.com/v3.0/docs/flutterwave-standard-1
     */
    public function testAmountIsSentInMajorUnits(): void
    {
        static::assertSame(100.5, $this->createFullPayload()->toArray()['amount']);
    }
}
