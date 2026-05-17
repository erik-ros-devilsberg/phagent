---
sprint: Fix tool_use.input empty-object round-trip in AnthropicClient
stories:
  - 07-tool-use-empty-input-round-trip
status: planned
created: 2026-05-17
---

## Goal

When Anthropic returns a `tool_use` block with `"input": {}`, PHP's `json_decode($body, true)` loses the object/array distinction (both `{}` and `[]` decode to PHP `[]`), and the next outgoing request encodes it back as `[]` — triggering an HTTP 400 from Anthropic's schema validator. This sprint closes that bug with a minimal, Anthropic-specific normalisation pass inside `AnthropicClient::sendMessages` — keeping the kernel's `array<string, mixed>` shape clean and `AgentLoop` unaffected. `OpenAIClient` already sidesteps the same quirk via `(object) $input` casting (sprint 04), so this is genuinely an Anthropic-adapter-only fix.

## Acceptance Criteria

- [ ] `AnthropicClientTest` has a new test (`testEmptyToolUseInputSerializedAsObject`) that passes a message list containing an assistant turn with a `tool_use` block whose `input` is PHP `[]`, calls `sendMessages`, captures the PSR-7 request body, and asserts the serialised `tool_use.input` is `{}` (a JSON object), not `[]`.
- [ ] A companion test (`testNonEmptyToolUseInputIsUnaffected`) verifies that a non-empty `input` (`['city' => 'Berlin']`) serialises unchanged.
- [ ] The fix lives exclusively in `AnthropicClient::sendMessages` — a private normalisation walk over `$messages` that rewrites any `content` block of `type === 'tool_use'` whose `input` is `[]` to `(object) []` immediately before `json_encode($payload, JSON_THROW_ON_ERROR)`. No changes to `AgentLoop`, `ClientInterface`, or any other file.
- [ ] `composer check` is green (PHPStan level 8, PHP-CS-Fixer, all 29+ tests passing, no regressions).
- [ ] `examples/run.php "what time is it"` executes the full Anthropic tool-use round trip against the live API and returns a final assistant text with no HTTP 400 on turn 2. (Manually verified by the developer; not part of CI.)
- [ ] A "Bug fixes" paragraph (or addendum to an existing sprint entry) is appended to `docs/system.md` explaining the PHP `json_decode` array/object ambiguity, where it surfaces in the Anthropic wire format, and the chosen fix (`(object) []` cast on outgoing payload normalisation).

## Tasks

- [ ] Write `testEmptyToolUseInputSerializedAsObject` in `tests/Client/AnthropicClientTest.php` — construct an assistant message with a `tool_use` content block, `input => []`, call `sendMessages`, assert the captured JSON body has `tool_use.input == {}`. (Red.)
- [ ] Write `testNonEmptyToolUseInputIsUnaffected` in the same file — same setup with `input => ['city' => 'Berlin']`, assert round-trip is exact. (Likely already passes — verifies non-regression.)
- [ ] Implement `normaliseMessages(array $messages): array` as a private method in `AnthropicClient`. Walk each message's `content` array; for any block where `type === 'tool_use'` and `input === []`, replace `input` with `(object) []`. Return the normalised list. Scoped to `tool_use` blocks only — `tools: []`, `messages: []`, and other `content: []` are correctly JSON arrays and must not be touched.
- [ ] Call `normaliseMessages($messages)` in `sendMessages` and pass the result into `$payload['messages']` instead of the raw `$messages`.
- [ ] Run `composer check` — confirm green, no PHPStan complaints (level 8 accepts `object`/`stdClass` inside `mixed` without annotation changes).
- [ ] Manual smoke test: `php examples/run.php "what time is it"` against the live Anthropic API — confirm two-turn success.
- [ ] Append a "Bug fixes" entry to `docs/system.md`.

## Risks and Open Questions

- **PHPStan + `(object) []` inside `array<string, mixed>`.** PHPStan level 8 accepts `\stdClass` as a value inside `mixed`, so no annotation change is needed on `normaliseMessages`'s return type or on `sendMessages`. If a complaint surfaces, narrow with a `/** @var array<string, mixed> $block */` at the cast site — no functional change.
- **Other Anthropic fields where `{}` matters.** The only other candidate is `input_schema.properties`, but `GetCurrentTimeTool::inputSchema()` already uses `(object) []` literally when constructing the schema — it is never round-tripped through `json_decode`, so it is already correct. No other Anthropic wire field uses an empty JSON object in a position where the array/object distinction matters.
- **Sprint size.** Genuinely small — one private method, two tests, one docs paragraph. ~30 minutes of work. Listed for completeness; not a real risk.
