# Contributing

Contributions are **welcome** and will be fully **credited**. Thank you for
helping improve this plugin.

By participating in this project you agree to abide by our
[Code of Conduct](CODE_OF_CONDUCT.md).

## Licensing of contributions

This project is licensed under the [Apache License 2.0](LICENSE). Under Section 5
of that license, **any contribution you submit is provided under the same
license**, unless you explicitly state otherwise. You retain the copyright to
your contribution.

We use the **Developer Certificate of Origin (DCO)** rather than a Contributor
License Agreement. The DCO is a lightweight, one-line assertion — added by
signing off your commits — that you have the right to submit the code under the
project license. There is no separate form to sign.

Sign off every commit:

```bash
git commit -s -m "fix: correct refund balance rounding"
```

`-s` appends a line like `Signed-off-by: Your Name <you@example.com>` using your
configured Git identity. The full text of what you are certifying is at
<https://developercertificate.org>. Pull requests whose commits are not signed
off cannot be merged.

## Trademarks

The code license does **not** grant rights to the KommandHub name or logo. If you
fork and redistribute your own version, you must rebrand it. See
[TRADEMARKS.md](TRADEMARKS.md).

## Reporting security issues

**Do not** open a public issue for a security vulnerability. Follow
[SECURITY.md](SECURITY.md) to report it privately.

## Branching strategy

- **`main`** — production-ready, released code.
- **`develop`** — integration branch; feature work is merged here first.
- **`feature/*`**, **`fix/*`** — branched from `develop`.

Open pull requests **against `develop`**, not `main`.

## Development setup

The plugin is developed inside the Docker dev stack:

```bash
make up        # build + start Shopware with the plugin mounted
make shell     # shell into the container
```

See the [README](README.md#local-development-setup) for full setup details.

## Before you open a pull request

Run the full local gate — every PR must pass all three:

```bash
make cs-fix && make analyse && make test
```

- **`make cs-fix`** — PHP-CS-Fixer (PSR-12, `.php-cs-fixer.dist.php`).
- **`make analyse`** — PHPStan level 9, zero errors.
- **`make test`** — PHPUnit; add or update tests alongside behaviour changes.

If you changed admin or storefront source (`Resources/app/**`), rebuild the
assets and commit the generated output (see the README's *Build & Asset
Compilation* section).

## Pull request guidelines

- **One pull request per feature or fix.** Keep changes focused.
- **Conventional Commits** for messages (`feat:`, `fix:`, `refactor:`, `test:`,
  `docs:`, `chore:`). This drives the changelog.
- **Document behaviour changes** — keep `README.md`, snippets, and
  `CHANGELOG.md` current.
- **Keep changes inside the relevant feature module**; reach across modules
  through services, not by deep-linking internals (see the README's
  *Architecture* and *Coding Standards* sections).
- **Send coherent history.** Squash noisy intermediate commits before
  submitting, and make sure each remaining commit is meaningful and signed off.
- **Do not include secrets** — never commit real API keys, secret hashes, or
  production data. Use test values.
