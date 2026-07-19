<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Client\Resource;

use Kommandhub\FlutterwaveV3SW\Exception\FlutterwaveException;

/**
 * Class Bank.
 *
 * Flutterwave's answer to Paystack's Verification resource. Note the shape
 * differs: Paystack resolves an account with GET /bank/resolve and query params,
 * Flutterwave uses POST /accounts/resolve with a JSON body, and its bank list is
 * scoped per country rather than global.
 *
 * @see https://developer.flutterwave.com/v3.0/docs/bank-account-verification
 */
class Bank extends ApiResource
{
    /**
     * Countries whose bank lists Flutterwave V3 serves.
     */
    public const SUPPORTED_COUNTRIES = ['NG', 'GH', 'KE', 'UG', 'ZA', 'TZ'];

    /**
     * List banks for a country (ISO 3166-1 alpha-2).
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function list(string $countryCode, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get('/banks/' . strtoupper($countryCode), [], $salesChannelId));
    }

    /**
     * List the branches of a bank. Required for some KE/UG banks.
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function branches(string $bankId, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->get("/banks/{$bankId}/branches", [], $salesChannelId));
    }

    /**
     * Resolve an account number to its holder's name.
     *
     * @return array<string, mixed>
     *
     * @throws FlutterwaveException
     */
    public function resolveAccount(string $accountNumber, string $bankCode, ?string $salesChannelId = null): array
    {
        return $this->response($this->httpClient->post('/accounts/resolve', [
            'account_number' => $accountNumber,
            'account_bank' => $bankCode,
        ], $salesChannelId));
    }
}
