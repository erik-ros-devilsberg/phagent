---
sprint: OpenAI provider adapter
story: docs/user-stories/backlog/04-openai-provider.md
created: 2026-05-14
---

## Goal

Add `Client\OpenAIClient` as a second implementation of `Client\ClientInterface` so the `AgentLoop` runs unchanged against either Anthropic or OpenAI. Honours the **Provider-agnostic** promise in `CLAUDE.md`. The forcing function: any Anthropic-specific shape leaking through `ClientInterface` will surface immediately and gets fixed at the port.

## Approach

This is the bigger of the two sprints because it almost certainly requires redesigning `ClientInterface`'s return shape into a provider-neutral protocol. Do the port redesign **first**, ship the OpenAI adapter **second**.

### Phase 1 — Neutral port

1. Read `Client/ClientInterface.php` and `Client/AnthropicClient.php`. Identify every Anthropic-specific shape that currently flows through the return value: the `content` block array shape, the `stop_reason` enum strings, the `tool_use` block structure, `tool_result` request shape.
2. Define a neutral internal protocol for `sendMessages` return:
   ```
   [
     'stop_reason' => 'end_turn' | 'tool_use' | 'max_tokens' | ...,
     'content'     => list<['type' => 'text', 'text' => string]
                       | ['type' => 'tool_use', 'id' => string, 'name' => string, 'input' => array]>,
   ]
   ```
   Anthropic-shaped because it's a superset of what OpenAI emits; OpenAI gets translated into it. The shape is documented as the kernel's internal protocol, not "Anthropic's shape."
3. Same for the *message list input*: define a neutral message shape that adapters translate from. Probably `['role' => 'user'|'assistant', 'content' => list<text-or-tool-use-or-tool-result-block>]`. Document it.
4. Refactor `AnthropicClient` so it does the translation on the way in and on the way out — `ClientInterface` no longer leaks Anthropic shapes.
5. `AgentLoop` should require zero changes; if it does, the port wasn't neutral.

### Phase 2 — OpenAI adapter

6. Add `Client\OpenAIClient` (Guzzle-backed, same shape as `AnthropicClient`).
7. `OpenAIClient::fromEnvironment()` reads `OPENAI_API_KEY`, throws if missing.
8. Pin model as `public const string MODEL = '<chosen-model>'` — pick during shaping (see Decisions below).
9. Translate the neutral message list → OpenAI Chat Completions request:
   - `role: user|assistant` maps directly.
   - `tool_use` block → `assistant` message with `tool_calls` array.
   - `tool_result` block → separate `role: tool` message with `tool_call_id` and `content`.
   - Tool schemas → OpenAI `tools` array (`type: function`, nested `function: {name, description, parameters}`).
10. Translate the OpenAI response → neutral return shape:
    - `finish_reason: stop` → `stop_reason: end_turn`.
    - `finish_reason: tool_calls` → `stop_reason: tool_use`, with each `tool_call` → `['type' => 'tool_use', ...]` block.
    - `finish_reason: length` → `stop_reason: max_tokens`.

## Test list (write red, then green)

- `AnthropicClient` request/response translation: existing tests still pass after refactor; if shapes changed, update assertions.
- `OpenAIClient` happy path: fake HTTP returns a text-only response → adapter returns neutral `['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => '...']]]`.
- `OpenAIClient` tool-call path: fake HTTP returns `finish_reason: tool_calls` with one tool call → adapter returns neutral `['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => ..., 'name' => ..., 'input' => [...]]]]`.
- `OpenAIClient` round-trip with `tool_result` in the input message list: assert the outgoing request body contains a `role: tool` message with the right `tool_call_id`.
- `OpenAIClient::fromEnvironment()` throws when `OPENAI_API_KEY` is unset.
- End-to-end with `AgentLoop` and a fake `OpenAIClient` running the existing `get_current_time` flow — kernel unchanged.

## Shaping decisions

- **Pick the OpenAI model.** Need a current Chat-Completions, tool-calling-capable model. Look up at shape time, pin as `const string`. (Don't guess — the agent rule applies.)
- **Neutral protocol shape.** Use the Anthropic-style block list (text / tool_use / tool_result) as the kernel's internal protocol. Reason: it's a superset; OpenAI translates cleanly into it; AgentLoop already understands it.
- **No new dependency.** Guzzle is already in; that's enough.
- **`examples/run.php` stays Anthropic-only for this sprint.** Provider selection in the example is a future concern (see story-04 acceptance — the flag was deliberately dropped).

## Definition of done

- `composer check` is green.
- Both `AnthropicClient` and `OpenAIClient` implement the redesigned `ClientInterface`.
- `AgentLoop` source is unchanged (or only touched to consume the new neutral types).
- `docs/system.md` gets an entry under a new "OpenAI provider + neutral client port (2026-05-14)" heading documenting the protocol shape and why it is what it is.
