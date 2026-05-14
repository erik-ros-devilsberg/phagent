---
story: Agent configuration and structured result
created: 2026-05-14
---

## Description

Today `AgentLoop::run(string $prompt): string` is the only knob. To do anything realistic — let alone replace the Claude-Code-in-subprocess agents on 1m.news (`StorySeedPicker`, `StoryResearcher`, `StoryWriter`) — three things are missing:

1. **System prompt.** Every real task has an identity ("you are a topic selector that returns JSON …"). Currently impossible to set.
2. **Per-instance LLM configuration.** Model and `max_tokens` are pinned as class constants on `AnthropicClient`. `MAX_TURNS` is pinned on `AgentLoop`. 1m.news uses a cheap haiku model for selection and a sonnet model for drafting; the kernel has to allow that.
3. **A structured result, not just a string.** Callers want at least the stop reason and the number of turns alongside the final text, so they can log, retry, or branch on what happened.

This story groups the three because they're one coherent API revision — splitting them would mean three back-to-back breaking changes to `AgentLoop` and `ClientInterface`.

## Acceptance Criteria

- `AnthropicClient` constructor takes `model` and `maxTokens` as parameters with sensible defaults (current pinned values become the defaults). The existing `MODEL` constant becomes `DEFAULT_MODEL`; the new `DEFAULT_MAX_TOKENS` constant exists.
- `AgentLoop` constructor takes `maxTurns` as a parameter with a default (current `MAX_TURNS` becomes `DEFAULT_MAX_TURNS`).
- `ClientInterface::sendMessages` accepts an optional `?string $systemPrompt = null`; `AnthropicClient` forwards it as the API's top-level `system` field when non-null.
- `AgentLoop::run(string $prompt, ?string $systemPrompt = null): AgentResult` — system prompt is per-call, not per-instance. (Wraps the same `system` field on the underlying call.)
- `Phagent\AgentResult` is a new `final readonly` value object exposing `text: string`, `stopReason: string`, `turns: int`. PHP-native, no framework types. Lives in `src/AgentResult.php`.
- Existing tests updated for the new return type; the no-tool and tool-use paths assert `AgentResult` fields, not bare strings.
- New tests cover: (a) system prompt threads through to `ClientInterface::sendMessages`, (b) configurable `maxTurns` actually changes the cap, (c) configurable `model` / `maxTokens` actually change the outgoing HTTP request body (via the existing fake Guzzle handler pattern in `AnthropicClientTest` if present, or a new test that asserts request body shape).
- `examples/run.php` updated to print `AgentResult` fields.
- `composer check` is green.
- No backwards-compat shims — pre-1.0, no external consumers.
