<?php

declare(strict_types=1);

namespace Kommandhub\FlutterwaveV3SW\Exception;

/**
 * Thrown when an admin refund request fails one of the plugin's own business
 * rules — the refund feature is disabled, the transaction is not in a
 * refundable state, the amount is outside the refundable range, and so on.
 *
 * Deliberately distinct from {@see FlutterwaveException}, which signals a
 * failure talking to the Flutterwave API itself. Keeping the two apart lets
 * callers tell "we rejected this request" apart from "Flutterwave (or the
 * network) failed while we were trying to act on it" — the two need
 * different HTTP handling and different log severity.
 *
 * The message is surfaced directly to the merchant in the Administration
 * refund dialog, so it must stay short, and free of stack traces or internal
 * identifiers.
 */
class RefundValidationException extends \RuntimeException
{
}
