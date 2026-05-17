---
story: Second example wired to Symfony HttpClient
created: 2026-05-17
---

## Description

Sprint 6 moved `AnthropicClient` onto the PSR-18 contract so consumers can bring their own HTTP client instead of installing Guzzle's transitive tree. That promise is currently invisible: the bundled `examples/run.php` calls `AnthropicClient::fromEnvironment()` which falls back to Guzzle, so a reader cannot tell whether the kernel really is HTTP-client-agnostic.

Add a second example that wires `Symfony\Component\HttpClient\Psr18Client` end-to-end — a one-class swap that satisfies all three PSR contracts the kernel needs (PSR-18 client, PSR-17 `RequestFactoryInterface` and `StreamFactoryInterface`). This serves as the executable proof of the framework-agnostic promise and as the template a Symfony-shop reader copies into their own bootstrap.

## Acceptance Criteria

- New runnable example file, e.g. `examples/run-symfony.php`, mirrors `examples/run.php`'s prompt-in / result-out behaviour but constructs `AnthropicClient` explicitly with a `Symfony\Component\HttpClient\Psr18Client` instance passed as the HTTP client and both factories. It must not call `AnthropicClient::fromEnvironment()`.
- `examples/run-symfony.php` does not reference any `GuzzleHttp\*` class — direct or transitive (via `use` statements). Running it must not autoload Guzzle.
- `composer.json` adds `symfony/http-client ^7` to `require-dev`. No production dependencies added.
- A short comment at the top of `examples/run-symfony.php` explains why the second example exists ("demonstrates phagent's PSR-18 port — swap to any compliant client, e.g. Symfony HttpClient, Buzz, or your framework's own").
- `examples/run-symfony.php` loads `.env` via the same `Dotenv` line used in `run.php`, so it works out of the box for anyone who configured the existing example.
- README gets a one-line pointer to the second example so it is discoverable.
- `composer check` is green. The example file itself is not linted or analysed (consistent with `examples/run.php` being outside PHP-CS-Fixer and PHPStan scope).
- A note appended to `docs/system.md` under the PSR-18 sprint entry (or a new short entry) explaining that the kernel is now demonstrably client-agnostic and how the second example proves it.

## Out of scope

- Provider selection plumbing (CLI flag to pick adapter). `examples/run-symfony.php` is its own file; no shared bootstrap or arg parsing.
- Removing Guzzle from `require-dev`. Guzzle still backs `AnthropicClient::fromEnvironment()`'s zero-arg fallback and the `AnthropicClientTest` PSR-17 fixtures.
- Auto-discovery (`php-http/discovery`). Adding a discovery layer is a separate decision; this story shows manual wiring, which is what real consumers do.
- A third example for Buzz or curl-client. One alternative is enough to prove the contract; more examples would dilute, not strengthen, the point.
