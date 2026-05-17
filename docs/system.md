# System Documentation

This file is maintained by `/agile:wrap-sprint`. Each section describes one completed sprint — what was built, why, and key decisions made. Read this to understand the system without reading all the code.

## Project Skeleton (2026-05-13)

**Stories:** [01-repo-organisation](user-stories/done/01-repo-organisation.md)

Established the PHP project foundation. Every later sprint builds on this.

### What was built

- `composer.json` — `php: ^8.4`, requires `guzzlehttp/guzzle:^7.9`; dev-requires `phpunit/phpunit:^11.4`, `phpstan/phpstan:^2.0`, `friendsofphp/php-cs-fixer:^3.64`.
- PSR-4 autoload: `Phagent\` → `src/`, `Phagent\Tests\` → `tests/`.
- `phpunit.xml` — strict config (`failOnWarning`, `failOnRisky`, `failOnNotice`, `failOnDeprecation`, `beStrictAboutOutputDuringTests`, `beStrictAboutChangesToGlobalState`), random execution order, `tests/` suite, source coverage scoped to `src/`.
- `phpstan.neon` — level 8 over `src/` and `tests/`.
- `.php-cs-fixer.php` — `@PSR12` + `@PHP84Migration` + `declare_strict_types` (risky rules allowed).
- Composer scripts: `test`, `lint`, `fix`, `analyse`, `check` (the last chains lint + analyse + test).
- `.gitignore`, `.editorconfig` (4-space LF), `README.md` documenting install, scripts, and single-test invocation.
- Placeholder `src/Phagent.php` and `tests/SmokeTest.php` to prove autoload + test wiring.
- `CLAUDE.md` "Working Notes" replaced with concrete Stack/Layout/Commands sections.

### Key decisions

- **PHPStan level 8, not max.** Level 9/max enforces "no `mixed` anywhere" and strict array shapes, which would create unnecessary friction at HTTP and JSON boundaries before the codebase exists. Ratchet later, once type boundaries settle.
- **PHP-CS-Fixer over PHP_CodeSniffer.** More active project, better PSR-12 + PHP-version-migration ruleset story.
- **PHPStan 2.x** (installed 2.1.54) — the current major. Config file uses the 2.x format.
- **`@PHP84Migration` ruleset** keeps style in step with the language version floor.
- **Strict PHPUnit defaults from day one** — `failOnDeprecation`, etc. Cheaper to enforce now than retrofit.

### Verification

`composer check` exits zero on the empty skeleton: PHP-CS-Fixer clean, PHPStan level 8 OK, PHPUnit 1/1 passing.

### Deferred / scope notes

- `bin/phagent` CLI entry point — deferred to the agent-loop sprint where it gains real behaviour.
- `config/` directory — skipped; create when the first config file actually lands.
- Git initialisation — explicitly skipped by user direction at the end of execution.

## Agent Loop (2026-05-13)

**Stories:** [02-agent-loop](user-stories/done/02-agent-loop.md)

Delivered the multi-turn tool-use cycle that drives the harness. Every future capability — more tools, memory, transcripts, a UI — hangs off this loop.

### What was built

- `src/AgentLoop.php` — the loop. Takes a `ClientInterface`, a `ToolRegistry`, and an optional `Logger`. Maintains the message list, dispatches `tool_use` blocks to registered handlers, feeds `tool_result` back as the next user turn, and terminates on any non-`tool_use` stop reason. Hard cap at 10 iterations (constant `MAX_TURNS`), throwing `LoopLimitException`.
- `src/Client/ClientInterface.php` — single `sendMessages(array $messages, array $tools): array`. Keeps the loop pure and lets tests inject anonymous fakes instead of mocking Guzzle.
- `src/Client/AnthropicClient.php` — Guzzle-backed implementation. Pins `claude-sonnet-4-6`, `max_tokens=1024`, anthropic-version `2023-06-01`. Factory `fromEnvironment()` reads `ANTHROPIC_API_KEY` and throws before any HTTP call if missing.
- `src/Tool/Tool.php`, `src/Tool/ToolRegistry.php`, `src/Tool/GetCurrentTimeTool.php` — interface, dict-backed registry (`register`/`get`/`allSchemas`), and the first concrete tool (returns `(string) time()`).
- `src/Logger.php`, `src/StdoutLogger.php` — per-turn logger abstraction. CLI wires `StdoutLogger`; tests pass `null` so they stay output-clean under strict PHPUnit config.
- `src/Exception/LoopLimitException.php` — typed exception for the iteration cap.
- `bin/phagent` — executable shebanged entry point. Reads prompt from `argv[1]` or stdin, wires `AnthropicClient` + `ToolRegistry` + `StdoutLogger`, prints the final answer.
- `tests/AgentLoopTest.php` (3 cases: no-tool path, single-tool path with `tool_result` round-trip assertions, iteration-cap exception). `tests/ToolRegistryTest.php` (3 cases).

### Key decisions

- **`ClientInterface` over Guzzle mocks.** A single-method PHP interface returning a decoded array is dramatically easier to fake than `GuzzleHttp\Psr7\Response` with a JSON body, and the test fakes read like the API protocol itself. Worth one extra file.
- **`Logger` abstraction.** Forced by `phpunit.xml`'s `beStrictAboutOutputDuringTests` + `failOnRisky`. Inline `fwrite(STDOUT, …)` would have made every loop test risky. Two-file cost (interface + stdout impl); the CLI is the only producer.
- **`fromEnvironment()` factory.** Reading `ANTHROPIC_API_KEY` lives in the factory, not in `sendMessages()`, so the constructor stays pure and the env-coupled code is identifiable by name.
- **PHPStan level 8 + JSON arrays.** Decoded API responses are walked through tight `is_string` / `is_array` guards (`stringField`, `listField` helpers in `AgentLoop`, key-loop validation in `AnthropicClient`). No `@phpstan-ignore` lines.
- **Model `claude-sonnet-4-6` pinned as class constant.** The shaping agent originally proposed a non-existent `claude-opus-4-5`; corrected during plan review. Pinned as a `public const string` so swaps are a one-line edit.
- **`bin/phagent` not linted.** Shebang + no `.php` extension trips PHP-CS-Fixer's default finder. File is tiny and reviewed; not worth a custom Finder configuration.

### Verification

`composer check` exits zero across 13 PHP files: PHP-CS-Fixer clean, PHPStan level 8 with zero errors, PHPUnit 7 tests / 18 assertions all green. The loop has not yet been smoke-tested against the live Anthropic API.

### Capabilities after this sprint

- Single-prompt CLI invocation: `bin/phagent "<prompt>"` (or piped on stdin) prints the final text answer.
- One registered tool: `get_current_time`. The loop will call it when the model asks and feed the timestamp back.
- Per-turn observability to stdout via `StdoutLogger`.

### Not yet supported (foreseeable next sprints)

- No conversation memory across invocations.
- No tool surface beyond `get_current_time` (no filesystem, shell, HTTP fetch, etc.).
- No streaming, no system prompt customisation, no runtime model selection.
- No live-API integration test — only stubbed-client unit tests.

## PSR-3 logging (2026-05-14)

**Stories:** [03-psr3-logging](user-stories/done/03-psr3-logging.md)

Replaced the project-internal `Phagent\Logger` interface with `Psr\Log\LoggerInterface`. Honours the **PSR-friendly** promise in `CLAUDE.md`: an embedding application (Laravel, Symfony, plain PHP) passes its own logger in; phagent doesn't impose one or ask the embedder to learn a custom interface.

### What was built / changed

- `composer.json` — added `psr/log: ^3` to `require`.
- `src/AgentLoop.php` — constructor now takes `?Psr\Log\LoggerInterface $logger = null`. Per-turn boundaries log at `info` (`'user prompt'`, `'assistant turn'` with `turn`, `summary`, `stop_reason` in context). Tool calls log at `debug` (`'tool call'` with `turn`, `tool`, `input`, `output` in context). The private `log()` helper is gone; call sites use `$this->logger?->info(...)` / `->debug(...)` directly.
- `src/Logger.php` and `src/StdoutLogger.php` — deleted. The kernel no longer ships a concrete logger.
- `examples/StdoutLogger.php` — new. `Phagent\Examples\StdoutLogger` extends `Psr\Log\AbstractLogger` and writes formatted lines (`[level] message {context-as-json}`) to STDOUT. ~20 LOC. Lives next to `run.php`, not in `src/`, because it's example wiring, not kernel.
- `examples/run.php` — explicit `require_once __DIR__ . '/StdoutLogger.php';` (the `examples/` directory is not autoloaded), instantiates the new example logger.
- `tests/AgentLoopTest.php` — added `testEmitsPsr3LogRecords`. Uses an inline `AbstractLogger` subclass that records every `log()` call; asserts both `info` and `debug` levels appear and that a debug record carries `tool: 'echo'` in its context.

### Key decisions

- **No external logger dependency.** `psr/log` is the interface only. A ~20-line stdout implementation in `examples/` beats pulling in Monolog for an example file.
- **Concrete logger lives in `examples/`, not `src/`.** The kernel exposes the PSR-3 port; concrete loggers belong to the embedder. Putting a default in `src/` would tempt future code to grow opinions about formatting and levels.
- **`examples/` is not PSR-4 autoloaded.** `run.php` `require_once`s the example logger explicitly. Keeps `Phagent\` namespace strictly aligned with `src/`. PHPStan and PHP-CS-Fixer already only scan `src/` and `tests/`, so the example file is intentionally outside both gates.
- **Log levels — `info` for boundaries, `debug` for payloads.** Nothing higher. The kernel does not decide what's a warning or error for the embedder; if a tool throws, that surfaces as an exception, not a log line.
- **No back-compat shim.** Old `Phagent\Logger` deleted outright; no deprecation period needed in a pre-1.0 library with no external consumers.

### Verification

`composer check` exits zero: CS-Fixer clean, PHPStan level 8 zero errors, PHPUnit 8 tests / 21 assertions green (was 7 / 18 — new test added one case, three assertions).

## Agent configuration and structured result (2026-05-14)

**Stories:** [05-agent-configuration](user-stories/done/05-agent-configuration.md)

Brought the public API surface up to the minimum needed for a Laravel app to drive task-specific agents (the 1m.news pattern: separate selector / researcher / drafter services, each with its own model, token budget, and system prompt). One coherent API revision: system prompt, configurable model / `max_tokens` / `max_turns`, and a structured `AgentResult` return.

### What was built / changed

- `src/AgentResult.php` — new `final readonly` value object with `public string $text`, `public string $stopReason`, `public int $turns`. PHP-native, three fields, no methods. The kernel's return shape from `AgentLoop::run`.
- `src/Client/ClientInterface.php` — `sendMessages` gains `?string $systemPrompt = null` as a third parameter. Breaking change to any implementer (only `AnthropicClient` and test fakes today).
- `src/Client/AnthropicClient.php`:
  - Constructor now takes `string $model = self::DEFAULT_MODEL` and `int $maxTokens = self::DEFAULT_MAX_TOKENS` as parameters. Old `MODEL` / `MAX_TOKENS` consts renamed to `DEFAULT_MODEL` / `DEFAULT_MAX_TOKENS`.
  - `sendMessages` reads `$this->model` and `$this->maxTokens` (not the constants) and adds a top-level `system` field to the payload when `$systemPrompt !== null`. The `system` key is omitted entirely when null — no `"system": null` on the wire.
- `src/AgentLoop.php`:
  - Constructor takes `int $maxTurns = self::DEFAULT_MAX_TURNS` as a fourth parameter; the loop bound is now `$this->maxTurns`. Const renamed `MAX_TURNS` → `DEFAULT_MAX_TURNS`.
  - `run(string $prompt, ?string $systemPrompt = null): AgentResult` — system prompt is per-call. Threaded to every `$this->client->sendMessages(...)` call. Natural completion path builds and returns an `AgentResult`; the iteration cap still throws `LoopLimitException`.
- `tests/AgentLoopTest.php` — existing happy-path tests updated to assert `AgentResult` fields (`text`, `stopReason`, `turns`) instead of bare strings. Two new tests: `testThreadsSystemPromptToClient` (anonymous client records `$systemPrompt`, asserts it matches the value passed to `run()`) and `testRespectsConfiguredMaxTurns` (constructs with `maxTurns: 2`, asserts `LoopLimitException` after exactly 2 client calls).
- `tests/Client/AnthropicClientTest.php` — new. Five tests asserting outgoing request body contains the right `model`, `max_tokens`, and `system` (or omits `system` when null), plus the empty-API-key rejection. Uses a custom Guzzle callable handler that captures `RequestInterface`s into a class property — avoids the type-narrowing trouble that `Middleware::history` causes at PHPStan level 8.
- `examples/run.php` — captures `$result = $loop->run(...)`, prints `$result->text` to stdout and `[turns=N stop_reason=…]` to stderr.

### Key decisions

- **System prompt is per-call.** Best fit for the use case: one `AgentLoop` instance per task, each `run()` invocation can vary the system prompt if needed. Callers who want a pre-baked prompt can wrap `AgentLoop` in their own service.
- **Model, `max_tokens`, `max_turns` are per-instance.** They're properties of "which agent this is," not of "what you're asking it right now." Matches how 1m.news instantiates one service per agent — and means you don't pass these on every call.
- **`AgentResult` has three fields and no methods.** Resisted adding a `metadata` array, cost tracking, or a `structured` JSON-decoded field. Each of those is a future story; speculative APIs rot fast. If a caller wants JSON, they `json_decode($result->text, true)` themselves — the kernel does not guess what shape they want.
- **Default constants live on the implementations, not the interface.** `DEFAULT_MAX_TURNS` is on `AgentLoop`; `DEFAULT_MODEL` / `DEFAULT_MAX_TOKENS` on `AnthropicClient`. The interface stays a contract, not a config carrier.
- **`fromEnvironment()` signature unchanged.** Adding model/maxTokens overrides to the factory would have meant either env-var conventions (`PHAGENT_MODEL`?) or a config object — both feel premature. Callers who want non-default model/tokens use the constructor directly.
- **Custom Guzzle handler over `Middleware::history` in tests.** The history middleware is typed `array|ArrayAccess` and forces PHPStan to widen the `&$container` reference, which broke a tighter return type on the test helper. A small callable handler that pushes captured requests into a `list<RequestInterface>` class property is both shorter and stays well-typed.
- **No back-compat shim on `ClientInterface`.** Third param added cleanly; no external implementations exist.

### Verification

`composer check` exits zero: CS-Fixer clean, PHPStan level 8 zero errors, PHPUnit 15 tests / 43 assertions green (was 8 / 21 — added 7 tests, 22 assertions across two test files).

### Capabilities after this sprint

- `new AnthropicClient($http, $apiKey, model: 'claude-haiku-4-5-20251001', maxTokens: 256)` — one cheap configuration per agent.
- `new AgentLoop($client, $tools, maxTurns: 2)` — bounded loop budget per agent.
- `$loop->run($userPrompt, $systemPrompt)` — task identity and per-call input cleanly separated, returns an `AgentResult` carrying `text`, `stopReason`, `turns`.

### Not yet supported (foreseeable next sprints)

- No JSON-schema / structured-output enforcement. Callers prompt for JSON in the system message and parse text themselves.
- No cost / token-usage metadata on `AgentResult`. The Anthropic response carries `usage` but it's discarded today.
- No way to swap the tool set per `run()` call — the registry is fixed at construction.
- `fromEnvironment()` still ignores model/tokens overrides.

## PSR-18 HTTP client port (2026-05-17)

**Stories:** [06-psr18-http-client](user-stories/done/06-psr18-http-client.md)

Replaced direct Guzzle coupling in `Client\AnthropicClient` with the standard PSR HTTP contracts (PSR-18 client, PSR-17 request/stream factories, PSR-7 messages). Guzzle moved from `require` to `require-dev`. Honours the **library-first, framework-agnostic, PSR-friendly** promises in `CLAUDE.md`: a Laravel or Symfony app embedding phagent now reuses the PSR-18 client it already has, instead of installing Guzzle's full transitive tree (`guzzlehttp/psr7`, `guzzlehttp/promises`, `ralouphie/getallheaders`, `symfony/deprecation-contracts`) as a second HTTP stack. Same philosophy the kernel applied to logging in the PSR-3 sprint.

### What was built / changed

- `composer.json` — `require` lost `guzzlehttp/guzzle ^7.9`, gained `psr/http-client ^1.0`, `psr/http-factory ^1.0`, `psr/http-message ^2.0`. `guzzlehttp/guzzle ^7.9` moved to `require-dev` so tests and `examples/run.php` still resolve.
- `src/Client/AnthropicClient.php`:
  - Constructor signature is now `(HttpClientInterface $http, RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory, string $apiKey, string $model = ..., int $maxTokens = ...)`. The Guzzle type hint is gone; no `GuzzleHttp\*` symbol appears anywhere in `src/`.
  - `sendMessages` builds a `Psr\Http\Message\RequestInterface` via the injected factories, sets `x-api-key`, `anthropic-version`, and `content-type` via `withHeader`, encodes the payload with `json_encode($payload, JSON_THROW_ON_ERROR)` into a stream, and dispatches via `$this->http->sendRequest($request)`. No `['json' => …]` Guzzle option array remains.
  - `fromEnvironment(?HttpClientInterface, ?RequestFactoryInterface, ?StreamFactoryInterface)` — keeps zero-argument ergonomics. When any of the three is missing it falls back to `\GuzzleHttp\Client` + `\GuzzleHttp\Psr7\HttpFactory` (Guzzle's PSR-7/17 implementation), guarded by a `class_exists` check that throws a clear `RuntimeException` if Guzzle is absent. This is the only place `GuzzleHttp\*` is referenced in `src/`, and consumers who supply their own stack never autoload the Guzzle classes.
- `tests/Client/AnthropicClientTest.php`:
  - Replaced the Guzzle `callable` handler with a small hand-rolled anonymous PSR-18 `ClientInterface` fake that captures the outgoing `RequestInterface` via a closure (avoids PHPStan's "property only written" complaint on a captured-by-reference list) and returns a canned `200` response via the test's PSR-17 factory.
  - New `testSendsPsr7RequestWithExpectedShape` asserts method, URI, and headers on the captured PSR-7 request.
  - New `testFromEnvironmentBuildsClientWithoutArguments` smoke-tests the guarded Guzzle fallback path without making an HTTP call. Backs up and restores `ANTHROPIC_API_KEY`.
  - Tests still depend on `GuzzleHttp\Psr7\HttpFactory` for convenience PSR-7/17 construction — acceptable because it's dev-scoped.
- `examples/run.php` — unchanged. `AnthropicClient::fromEnvironment()` continues to work with no arguments.

### Key decisions

- **PSR-17 factories injected, not pulled from a global locator.** Adding `php-http/discovery` would let `fromEnvironment()` magically find any installed PSR-17 implementation, but it's another transitive dep and another layer of indirection. Direct injection plus a guarded Guzzle fallback covers the two real cases (consumer brings their own; or runs the example/dev install). If a real user ever asks for discovery, add it then.
- **Three constructor args for HTTP, not one bundled config object.** The three PSR interfaces are the boundary; bundling them in a wrapper class would re-invent what PSR-17 already standardises and make the constructor harder to satisfy from a DI container.
- **Guzzle stays in `require-dev`, not removed entirely.** It backs `fromEnvironment()`'s fallback and the test fakes' PSR-7/17 plumbing. Removing it would force `examples/run.php` to wire factories explicitly, which is exactly the friction the factory method exists to avoid.
- **`fromEnvironment()` keeps zero-argument ergonomics.** A library that demands consumers wire three PSR factories before they can `getting started` loses to one that "just works" against the demo. The `class_exists` guard means the convenience is opt-in, not a hard dependency.
- **Hand-rolled PSR-18 test fake over `MockHandler` + adapter.** An anonymous class implementing one method (`sendRequest`) is ~12 lines and reads as the test's intent. Wrapping `MockHandler` would re-introduce a Guzzle type in the test for no readability gain.
- **`JSON_THROW_ON_ERROR` on the encode.** Returns `string` (not `string|false`), so PHPStan level 8 needs no guard. Encoding failures throw `JsonException` rather than silently producing an empty body.

### Verification

`composer check` exits zero: CS-Fixer clean, PHPStan level 8 zero errors, PHPUnit 17 tests / 50 assertions green (was 15 / 43 — added 2 tests, 7 assertions). `composer why guzzlehttp/guzzle` confirms the package is required only "for development" by `devilsberg/phagent`, and `guzzlehttp/psr7` + `guzzlehttp/promises` are required only by Guzzle itself — so `composer install --no-dev` pulls zero Guzzle packages.

### Capabilities after this sprint

- Consumers with an existing PSR-18 stack (Symfony HttpClient, Buzz, Guzzle, etc.) construct `new AnthropicClient($theirClient, $theirRequestFactory, $theirStreamFactory, $apiKey, …)` and pull zero Guzzle packages.
- `AnthropicClient::fromEnvironment()` continues to "just work" out of the box for `examples/run.php` and CLI use, provided Guzzle is installed (which it is, as a dev dep, in any cloned working tree).
- The kernel's only HTTP-related production dependencies are the three PSR contracts.

### Not yet supported (foreseeable next sprints)

- No PSR-18 discovery (`php-http/discovery`) — if Guzzle is absent and the consumer doesn't pass factories, `fromEnvironment()` throws.
- The OpenAI adapter (sprint 04, still pending) will need the same PSR-18 treatment; its plan's "no new dependency" shaping note must be revisited when executed.

## OpenAI provider adapter (2026-05-17)

**Stories:** [04-openai-provider](user-stories/done/04-openai-provider.md)

Added `Client\OpenAIClient` as the second implementation of `Client\ClientInterface`. Lands the **provider-agnostic** promise from `CLAUDE.md`: `AgentLoop` runs unchanged against either Anthropic or OpenAI (and, through `$baseUrl`, against every OpenAI-compatible endpoint — Groq, Together, OpenRouter, vLLM, llama.cpp's OpenAI mode, Ollama's `/v1/chat/completions` shim, Alibaba DashScope's OpenAI mode for Qwen, etc.). The forcing function worked as intended: the act of writing a second adapter exposed exactly which fields of the `ClientInterface` return shape the loop actually depends on, and made the neutral protocol explicit at the interface level.

### What was built / changed

- `src/Client/ClientInterface.php` — docblock now formally documents the kernel's neutral internal protocol: the `$messages` block shapes (`text` / `tool_use` / `tool_result`), the `$tools` schema shape, and the `sendMessages` return shape (`stop_reason` string + `content` list of `text` / `tool_use` blocks). No code change — the existing return was already neutral; the docblock made the implicit contract explicit.
- `src/Client/OpenAIClient.php` — new. Constructor signature mirrors `AnthropicClient` (PSR-18 client + PSR-17 `RequestFactoryInterface` + `StreamFactoryInterface` + `$apiKey`), plus `$model = self::DEFAULT_MODEL`, `$baseUrl = self::DEFAULT_BASE_URL`, `$maxTokens = self::DEFAULT_MAX_TOKENS`. No `GuzzleHttp\*` types except in the guarded `fromEnvironment()` fallback.
  - `DEFAULT_MODEL = 'gpt-4o'` — broadly-available tool-calling-capable Chat Completions model. One-line swap for newer / cheaper choices.
  - `DEFAULT_BASE_URL = 'https://api.openai.com/v1/chat/completions'`. The PSR-7 request URI is built from `$this->baseUrl` (not the constant) so any compatible endpoint works.
  - `fromEnvironment()` reads `OPENAI_API_KEY` (required) and `OPENAI_BASE_URL` (optional, defaults to OpenAI), with the same guarded Guzzle PSR-18 fallback as `AnthropicClient`.
  - `sendMessages()` translates the neutral message list → OpenAI Chat Completions request and the OpenAI response → neutral shape. Wire-format mapping:
    - Outgoing: `text` blocks concatenate into `message.content`; `tool_use` blocks become `message.tool_calls[*].function` entries with `arguments` JSON-encoded as `(object) $input` so empty input round-trips as `{}` (not `[]`); `tool_result` blocks split off into separate `role: tool` messages with `tool_call_id` and a flattened (string-or-JSON-encoded) `content`; an optional `$systemPrompt` lands as a `role: system` message at index 0.
    - Incoming: `choices[0].message.tool_calls` map to neutral `tool_use` blocks, with `arguments` JSON-decoded back into the `input` array; `choices[0].message.content` becomes a single `text` block; `finish_reason` maps `stop → end_turn`, `tool_calls → tool_use`, `length → max_tokens` (other values pass through unchanged).
  - Non-2xx responses throw `RuntimeException` carrying the full body, consistent with the post-sprint-06 `AnthropicClient` error surface.
- `tests/Client/OpenAIClientTest.php` — new. 12 tests / 30 assertions using the same hand-rolled anonymous PSR-18 fake pattern as `AnthropicClientTest`. Covers: text happy path, tool-call response → neutral shape, empty `arguments` → empty array, `tool_result` round-trip (asserts the assistant `tool_calls` and `role: tool` shapes on the captured outgoing PSR-7 request), `fromEnvironment()` missing-key throw, `fromEnvironment()` smoke (with key set), non-default `$baseUrl` honoured on outgoing URI, `OPENAI_BASE_URL` env honoured, empty-key constructor rejection, `Bearer` authorization header, `finish_reason: length` → `max_tokens`, tool-schema translation. All env-var tests back up and restore `OPENAI_API_KEY` / `OPENAI_BASE_URL` to keep the suite hermetic.

### Key decisions

- **Neutral protocol canonicalised, not redesigned.** Phase 1 of the sprint plan anticipated a possible `ClientInterface` redesign. In practice the existing return shape was already neutral (Anthropic's block list is a strict superset of what OpenAI emits); only the docblock needed updating. `AgentLoop` was not touched.
- **`json_encode((object) $input)` for outgoing `tool_calls[*].function.arguments`.** OpenAI requires `arguments` to be a JSON-encoded *string* that decodes to an *object*. PHP's `json_encode([])` gives `"[]"`, which would round-trip as a JSON array on the wire and trip schema validators. Casting to `(object)` forces `"{}"` for empty input — proactively avoids the same `tool_use.input` round-trip bug that story 07 will fix for the Anthropic adapter.
- **`tool_result` content flattened to a string at the adapter boundary.** OpenAI's `role: tool` message expects `content` as a string. The adapter passes plain strings through unchanged and `json_encode`s any non-string content. The neutral protocol stays simple (content is `string`); the wire-format quirk lives in the translator where it belongs.
- **Bearer auth, not `x-api-key`.** OpenAI's convention. Lives entirely inside the adapter; the kernel knows nothing about either header.
- **`max_tokens` is per-instance, not per-call.** Same pattern as `AnthropicClient` (post-sprint-05). Sized to a sensible 1024 default; one constructor arg away from any other budget.
- **`$baseUrl` from day one.** Adding it later would have been a breaking constructor change. Costs one extra parameter, one extra test pair (constructor + env), and unlocks every OpenAI-compatible endpoint without a second adapter. Verified end-to-end via the captured PSR-7 request URI in `testNonDefaultBaseUrlIsHonouredOnOutgoingRequest` and `testFromEnvironmentHonoursOpenAiBaseUrlEnv`.
- **Smoke-test coverage for compatible endpoints is deferred to consumers.** The plan called for a "two or three endpoints smoke-tested" note; in practice the unit-test coverage of `$baseUrl` substitution proves the contract, and live smoke tests against Groq / Together / DashScope require API keys and network access that don't belong in `composer check`. Consumers point `OPENAI_BASE_URL` at their endpoint and run `examples/run.php` (after sprint 08's CLI provider-selection lands, or via a small custom bootstrap today).

### Verification

`composer check` exits zero: CS-Fixer clean, PHPStan level 8 zero errors, PHPUnit **29 tests / 80 assertions** all green (was 17 / 50 — added 12 tests, 30 assertions). No new production dependencies; `composer.json` `require` unchanged from sprint 06.

### Capabilities after this sprint

- `new OpenAIClient($http, $reqFactory, $streamFactory, $apiKey)` — drop-in second provider for the `AgentLoop`.
- `OpenAIClient::fromEnvironment()` — zero-arg construction with `OPENAI_API_KEY` (and optional `OPENAI_BASE_URL`) from env / `.env`.
- Configurable `$baseUrl` lets one adapter target OpenAI, Groq, Together, OpenRouter, Fireworks, vLLM, llama.cpp's OpenAI server, Ollama's OpenAI shim, and Alibaba DashScope's OpenAI mode (Qwen) — without a second adapter.
- `ClientInterface` is now a documented, neutral port. Any future adapter (Ollama native, DashScope native, etc.) translates to and from the same shape.

### Not yet supported (foreseeable next sprints)

- Live smoke tests against compatible endpoints are not part of CI. Consumer responsibility.
- Streaming responses (OpenAI supports SSE; `ClientInterface` is request/response only).
- Multi-modal inputs (images, audio).
- Token-usage / cost reporting on `AgentResult` — OpenAI returns `usage` but the kernel discards it.
- `examples/run.php` is still Anthropic-only. Provider selection in the example is sprint 08 territory (see backlog).
- Story 07 (`tool_use.input` empty-object round-trip) remains open for `AnthropicClient`. `OpenAIClient` sidesteps it via `(object) $input` casting at the adapter boundary — but the kernel-side fix for Anthropic still belongs in story 07.

## Bug fix — `tool_use.input` empty-object round-trip (2026-05-17)

**Stories:** [07-tool-use-empty-input-round-trip](user-stories/done/07-tool-use-empty-input-round-trip.md)

Closed a defect that blocked `examples/run.php` against the live Anthropic API whenever the model called a tool with no arguments (e.g. `GetCurrentTimeTool`). Root cause is PHP's lossy JSON decode: `json_decode($body, true)` collapses both `{}` and `[]` to the same PHP `[]`. The Anthropic response carries `tool_use.input` as a JSON object; the kernel echoes the decoded `content` blocks back on the next turn; `json_encode` then emits `"input":[]`, and Anthropic's schema validator rejects the request with `HTTP 400: messages.<n>.content.<m>.tool_use.input: Input should be an object`. The bug only surfaces for tools with no parameters — tools with arguments fill `input` with a non-empty object that round-trips correctly.

### What was built / changed

- `src/Client/AnthropicClient.php` — new private `normaliseMessages(array $messages): array` walker called from `sendMessages()` immediately before building the request payload. Scans each message's `content` list; for any block where `type === 'tool_use'` and `input === []`, rewrites `input` to `(object) []` so `json_encode` emits `{}` on the wire. Scoped to `tool_use` blocks only — `tools: []`, `messages: []`, and other `content: []` arrays are correctly JSON arrays and left untouched.
- `tests/Client/AnthropicClientTest.php` — two new tests: `testEmptyToolUseInputSerializedAsObject` (string-asserts `"input":{}` is present and `"input":[]` is absent in the captured PSR-7 request body) and `testNonEmptyToolUseInputIsUnaffected` (regression guard: `['city' => 'Berlin']` round-trips exactly).

### Key decisions

- **Fix at the outgoing-payload boundary, not at the decode boundary.** Normalising at decode time would force the kernel's `array<string, mixed>` typed shape to accept `\stdClass` in one specific position, rippling through `AgentLoop` and its PHPStan annotations for no real gain. The wire-format quirk belongs in the wire-format adapter.
- **Adapter-only fix, not kernel-side.** `OpenAIClient` already sidesteps the same quirk by casting `(object) $input` when JSON-encoding `tool_calls[*].function.arguments` (sprint 04). Each adapter handles its own provider's wire-format requirements; `AgentLoop` and `ClientInterface` stay neutral.
- **No change to `ClientInterface` or `AgentLoop`.** The defect is entirely inside `AnthropicClient`; nothing else needed touching.

### Verification

`composer check` exits zero: CS-Fixer clean, PHPStan level 8 zero errors (`\stdClass` is accepted inside `array<string, mixed>` without annotation tweaks, as expected), PHPUnit **31 tests / 90 assertions** all green (was 29 / 80 — added 2 tests, 10 assertions). Live-API smoke test of `examples/run.php "what time is it"` against Anthropic confirmed the full tool-use round trip: turn 1 returns `stop_reason: tool_use`, the loop runs `get_current_time` with `input: []`, turn 2 receives the timestamp result and returns `stop_reason: end_turn` with a natural-language answer — no HTTP 400.

