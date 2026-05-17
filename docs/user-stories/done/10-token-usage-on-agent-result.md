---
story: Token usage on AgentResult
created: 2026-05-17
---

## Description

Both `AnthropicClient` and `OpenAIClient` receive a `usage` block in every response (Anthropic: `usage: {input_tokens, output_tokens, ...}`; OpenAI: `usage: {prompt_tokens, completion_tokens, total_tokens}`). The kernel currently discards it. Consumers that track per-agent cost (the canonical 1m.news pattern: one service per agent, model and budget per service) have no way to retrieve usage data short of subclassing the client or sniffing the wire.

Add token usage to `AgentResult` so every `AgentLoop::run()` call returns a cost-trackable result. Accumulate across all turns of a single run, not just the final turn — a tool-use loop spans multiple `sendMessages` calls and the consumer cares about the total.

## Acceptance Criteria

- `Phagent\AgentResult` gains two new `public readonly` fields: `int $inputTokens` and `int $outputTokens`. Existing fields (`text`, `stopReason`, `turns`) unchanged.
- `Client\ClientInterface::sendMessages` return shape gains a new key in the neutral protocol: `'usage' => ['input_tokens' => int, 'output_tokens' => int]`. Docblock on `ClientInterface` updated to document this. When a response omits `usage` (errors, providers that don't return it), the adapter returns `'usage' => ['input_tokens' => 0, 'output_tokens' => 0]` — no exception.
- `AnthropicClient` populates `usage` from `usage.input_tokens` + `usage.output_tokens` on every response.
- `OpenAIClient` populates `usage` from `usage.prompt_tokens` + `usage.completion_tokens` on every response.
- `AgentLoop` sums `input_tokens` and `output_tokens` across every `sendMessages` call within a single `run()` and passes the totals to `AgentResult`.
- New tests:
  - `AnthropicClientTest`: response with `usage` block → adapter returns the right `usage` shape; response without `usage` → adapter returns zeros.
  - `OpenAIClientTest`: same pair of cases.
  - `AgentLoopTest`: multi-turn run accumulates usage across all turns (e.g. turn 1: 100 in / 50 out → tool_use, turn 2: 200 in / 30 out → end_turn ⇒ `AgentResult::$inputTokens === 300`, `$outputTokens === 80`).
- `composer check` is green.
- New entry in `docs/system.md` documenting the protocol addition and the accumulation rule.

## Out of scope

- **Cost calculation** (input × $/Mtok + output × $/Mtok). Provider pricing is volatile and consumer-specific; surfacing raw token counts is enough — the consumer multiplies.
- **Cache token tracking.** Anthropic returns `cache_creation_input_tokens` and `cache_read_input_tokens`; OpenAI returns `prompt_tokens_details.cached_tokens`. These matter for cost when prompt caching is enabled, but the kernel doesn't use prompt caching today. Flag for a follow-up story when caching is added; out of scope here.
- **Per-turn breakdown.** Just accumulated totals. A `list<array>` of per-turn usage adds API surface for a use case (debugging which turn was expensive) nobody has asked for yet.
- **Reasoning-token accounting** (OpenAI o1/o3 family's `reasoning_tokens` field). Belongs in the same future-story bucket as cache tokens.
- **Cost on `AgentResult`** as a computed property. Same reasoning as cost calculation above — kernel stays cost-agnostic, consumer multiplies.
