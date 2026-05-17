---
story: Preserve empty tool_use.input as JSON object on round-trip
created: 2026-05-17
---

## Description

When the Anthropic API returns an assistant turn that contains a `tool_use` block with an empty input (`"input": {}`), the conversation cannot be continued: the next request to the API is rejected with

```
HTTP 400: messages.<n>.content.<m>.tool_use.input: Input should be an object
```

Root cause is a PHP/JSON round-trip quirk in `Client\AnthropicClient::sendMessages`:

1. Response is decoded with `json_decode($body, true)` — associative mode. An empty JSON object `{}` is indistinguishable from an empty JSON array `[]` and both decode to PHP `[]`.
2. `AgentLoop` appends the decoded `content` to its message list and hands it back to `sendMessages` on the next turn.
3. `json_encode($payload, JSON_THROW_ON_ERROR)` serialises PHP `[]` as JSON `[]`. Anthropic's schema requires `tool_use.input` to be a JSON object and rejects the request.

The bug surfaces whenever a tool has no parameters — e.g. `GetCurrentTimeTool` (`inputSchema()` returns `properties: (object) []`). It does **not** surface for tools that take arguments, because the model fills the input with a non-empty object.

This blocks the bundled `examples/run.php` demo when the model decides to call `get_current_time`.

## Acceptance Criteria

- `examples/run.php "what time is it"` runs the full tool-use round trip against the live Anthropic API and returns a final assistant text — no HTTP 400 on the second request.
- The fix lives in `Client\AnthropicClient` (it's an Anthropic-specific wire-format quirk; the kernel's `array<string, mixed>` shape should not be polluted by adapter concerns).
- Outgoing request bodies sent by `AnthropicClient::sendMessages` serialise an empty `tool_use.input` as `{}`, not `[]`. Verified by an assertion on the captured PSR-7 request body in `AnthropicClientTest`.
- Non-empty `tool_use.input` values (`['city' => 'Berlin']` etc.) are unaffected.
- No regression on existing 17 tests; `composer check` is green.
- One-paragraph note appended to the PSR-18 sprint entry in `docs/system.md` (or a new "Bug fixes" entry), explaining the round-trip quirk and the chosen fix.

## Out of scope

- Generalised "preserve empty JSON objects everywhere" handling. Only `tool_use.input` is part of Anthropic's documented schema where an empty object is meaningful; other empty fields (`tools: []`, `messages: []`, content arrays) are correctly arrays.
- Switching the response decoder to `json_decode(..., false)` (object mode). That would ripple through `AgentLoop`'s `array<string, mixed>` types and PHPStan level-8 assertions for no real gain.
- The OpenAI adapter (sprint 04, still pending). Its tool-call request shape is different and will need its own analysis when that sprint is executed.
