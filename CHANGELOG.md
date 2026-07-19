# 0.9.0-beta.1

Pre-release for internal development, QA and sandbox/staging testing. Not yet
submitted to the Shopware Store. The public API and namespaces may still
change before `1.0.0`.

- Flutterwave payment for Shopware 6: card, bank transfer and mobile money.
- Payment verification checks status, amount and currency before an order is marked paid.
- Refunds from the order detail page, including partial refunds, with a live refund history and a server-side over-refund guard.
- Dedicated "Flutterwave refund" admin permission that can be assigned to roles (depends on the order editor permission).
- Webhook handling for `charge.completed` and `refund.completed`, with signature verification and idempotent, replay-safe processing.
- Bank-account verification in the customer account (account resolution via Flutterwave), with an optional BVN field.
- Amounts are sent to Flutterwave in major units, as its API expects, and compared exactly using each currency's own decimal precision — including zero- and three-decimal currencies (e.g. RWF, UGX, KWD). The plugin does not create currencies or languages in the shop.
- Plugin interface translated into English, German and French.
- Configurable logging (scoped per sales channel), sandbox/live mode and a minimum refund amount.
- Supports Shopware 6.6 and 6.7.
