<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Checkout\Payment\Struct;

/**
 * Payload for a Flutterwave V3 Standard checkout (POST /payments).
 *
 * Ported from the kommandhub/flutterwave-v3 SDK's Payloads\PaymentPayload so
 * the plugin carries no third-party runtime dependency. The SDK modelled
 * customer and customizations as their own payload classes over a generic
 * get/set bag; both are flat leaf structures used only from here, so they are
 * typed constructor arguments instead — same wire output, three fewer classes
 * and no untyped mutation.
 *
 * @see https://developer.flutterwave.com/v3.0/docs/flutterwave-standard-1
 */
final class PaymentPayload
{
    /**
     * @param float $amount MAJOR units. Flutterwave V3 charges what it is given:
     *                      100.0 with currency NGN is ₦100, not ₦1. Never pass a
     *                      minor-unit value here.
     * @param string $txRef Merchant-generated reference. Webhooks are keyed by this.
     */
    public function __construct(
        private readonly float $amount,
        private readonly string $currency,
        private readonly string $txRef,
        private readonly string $redirectUrl,
        private readonly string $customerEmail,
        private readonly ?string $customerName = null,
        private readonly ?string $customerPhone = null,
        private readonly ?string $title = null,
        private readonly ?string $logo = null,
        private readonly ?string $description = null,
    ) {
    }

    public function getTxRef(): string
    {
        return $this->txRef;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tx_ref' => $this->txRef,
            'redirect_url' => $this->redirectUrl,
            'customer' => array_filter([
                'email' => $this->customerEmail,
                'name' => $this->customerName,
                'phonenumber' => $this->customerPhone,
            ], static fn (?string $value): bool => $value !== null),
            // Customizations are optional presentation fields sourced from config
            // via SystemConfigService::getString(), which coerces an unset value
            // to '' rather than null. Filtering on null alone would let an
            // unconfigured logo through as "logo": "" instead of omitting the key;
            // drop empties too so only a real value is ever sent.
            'customizations' => array_filter([
                'title' => $this->title,
                'logo' => $this->logo,
                'description' => $this->description,
            ], static fn (?string $value): bool => $value !== null && $value !== ''),
        ];
    }
}
