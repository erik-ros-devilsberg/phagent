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

