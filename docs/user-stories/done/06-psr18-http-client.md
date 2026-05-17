---
story: PSR-18 HTTP client port (move Guzzle to dev)
created: 2026-05-17
---

## Description

`Client\AnthropicClient` currently type-hints `GuzzleHttp\ClientInterface` directly and `composer.json` lists `guzzlehttp/guzzle` under `require`. This forces every consumer of `phagent` to install Guzzle and its full transitive tree (`guzzlehttp/psr7`, `guzzlehttp/promises`, `psr/http-*`, `ralouphie/getallheaders`, `symfony/deprecation-contracts`) regardless of what HTTP client they already use in their own app.

That violates the **library-first, framework-agnostic** promise in `CLAUDE.md`. The kernel should depend on PSR interfaces and let the consumer bring the HTTP client — the same way it already depends on `psr/log` instead of bundling a logger. Most serious PHP apps already have a PSR-18 client available (Symfony HttpClient, Guzzle, Buzz, kriswallsmith/buzz, etc.); we shouldn't force a second one on them.

Move Guzzle from `require` to `require-dev` so it remains available for tests and `examples/run.php`, but is not a transitive burden on consumers.

## Acceptance Criteria

- `composer.json` lists `psr/http-client` (PSR-18), `psr/http-factory` (PSR-17), and `psr/http-message` (PSR-7) under `require`. `guzzlehttp/guzzle` moves to `require-dev`.
- `Client\AnthropicClient::__construct` takes `Psr\Http\Client\ClientInterface` plus a `Psr\Http\Message\RequestFactoryInterface` and `Psr\Http\Message\StreamFactoryInterface` (so the adapter can build requests without depending on a concrete client). No `GuzzleHttp\*` types appear anywhere in `src/`.
- `AnthropicClient::sendMessages` builds a PSR-7 request via the injected factories, sends it via the PSR-18 client, and reads the PSR-7 response — no `['json' => ...]` Guzzle-isms.
- `AnthropicClient::fromEnvironment()` continues to work out of the box: if no client/factories are passed, it falls back to Guzzle's PSR-18 implementation (Guzzle satisfies all three PSR contracts) — this is the only place Guzzle may be referenced in `src/`, and it must be guarded so users who supply their own client never load the Guzzle classes.
- Existing tests still pass; they may continue to use Guzzle's `MockHandler` since tests are dev-scoped.
- `examples/run.php` runs unchanged — `fromEnvironment()` still produces a working client without the user wiring factories.
- `composer check` is green. `composer install --no-dev` of a fresh checkout pulls **no** Guzzle packages.
- `docs/system.md` gets a new entry documenting the port shape and the rationale (one paragraph, mirror the existing entries).
