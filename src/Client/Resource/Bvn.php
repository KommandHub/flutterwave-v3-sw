<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Exception\FlutterwaveException;

/**
 * Class Bvn.
 *
 * BVN (Bank Verification Number) verification — a Flutterwave capability Paystack
 * does not expose. Flutterwave models it as a consent flow, not a lookup: the
 * merchant initiates a verification, the customer consents on a Flutterwave-hosted
 * portal, and only then can the resolved identity be retrieved.
 *
 * Retrieving the resolved identity additionally requires Flutterwave to approve
 * the merchant account ("email support to enable BVN resolution"), and returns
 * highly sensitive PII (NIN, date of birth, face image). This plugin therefore
 * uses BVN only to *initiate* consent-based verification and never pulls that PII
 * into the shop. See BankVerificationController for how the reference is used.
 *
 * @see https://developer.flutterwave.com/v3.0.0/docs/bvn-verification
 */
class Bvn extends ApiResource
{
    /**
     * Initiate a consent-based BVN verification.
     *
     * Returns Flutterwave's consent portal URL and a reference. The customer must
     * visit the URL and consent; the reference identifies the verification later.
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function initiate(
        string $bvn,
        string $firstName,
        string $lastName,
        string $redirectUrl,
        ?string $salesChannelId = null
    ): array {
        return $this->response($this->httpClient->post('/bvn/verifications', [
            'bvn' => $bvn,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'redirect_url' => $redirectUrl,
        ], $salesChannelId));
    }

    /**
     * Retrieve the status of a previously initiated verification.
     *
     * Only the verification status is read — never the resolved identity bundle,
     * which is sensitive PII and gated behind Flutterwave merchant approval.
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function fetch(string $reference, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get("/bvn/verifications/{$reference}", [], $salesChannelId));
    }
}
