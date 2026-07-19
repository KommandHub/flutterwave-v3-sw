<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveSW\Webhook\Service;

use Kommandhub\FlutterwaveSW\Setting\Service\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Validates the authenticity of an incoming Flutterwave webhook.
 *
 * Flutterwave does NOT sign the payload. It echoes back the static secret hash
 * configured in the dashboard, verbatim, in a `verif-hash` header:
 *
 *   "If you specify a secret hash, we'll include it in our request to your
 *    webhook endpoint, in a header called verif-hash."
 *
 * @see https://developer.flutterwave.com/v3.0.0/docs/webhooks
 *
 * The security consequence is important and drives the rest of this module: a
 * valid header proves only that the *sender* knows the shared secret — it says
 * nothing about the body, which is not covered by any signature. Anyone who
 * observes one genuine delivery can replay a modified body with the same header
 * and pass this check. Unlike an HMAC scheme (Paystack, Flutterwave V4), the
 * header cannot be used to trust the payload's contents.
 *
 * Therefore this validator is a coarse gate against unauthenticated traffic
 * only. Subscribers must never act on payload values; they re-fetch the
 * authoritative state from the Flutterwave API before mutating an order.
 */
class WebhookSignatureValidator
{
    public const SIGNATURE_HEADER = 'verif-hash';

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @throws AccessDeniedHttpException when the request is not provably from Flutterwave
     */
    public function validate(Request $request, ?string $salesChannelId = null): void
    {
        $secretHash = $this->config->getSecretHash($salesChannelId);

        if ($secretHash === '') {
            // Fail closed. Without a configured hash every caller would be
            // accepted, so an unconfigured plugin must reject rather than trust.
            throw new AccessDeniedHttpException('Flutterwave webhook secret hash is not configured.');
        }

        $signature = $request->headers->get(self::SIGNATURE_HEADER);

        if (!is_string($signature) || $signature === '') {
            throw new AccessDeniedHttpException('Missing Flutterwave verif-hash header.');
        }

        // Constant-time compare: the header is a secret, so a timing-based
        // comparison would leak it byte by byte.
        if (!hash_equals($secretHash, $signature)) {
            throw new AccessDeniedHttpException('Invalid Flutterwave verif-hash header.');
        }
    }
}
