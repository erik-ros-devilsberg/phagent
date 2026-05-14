---
story: OpenAI provider adapter
created: 2026-05-14
---

## Description

`Client\AnthropicClient` is currently the only implementation of `Client\ClientInterface`. The "provider-agnostic kernel" promise in `CLAUDE.md` is theoretical until a second adapter exists. Adding one forces the port to actually be a port — any leak of Anthropic-specific shapes (e.g. content-block arrays, stop-reason strings) surfaces immediately and gets fixed at the interface level.

Add an OpenAI Chat Completions adapter that speaks the same `ClientInterface` so `AgentLoop` runs unchanged against either provider.

## Acceptance Criteria

- `Client\OpenAIClient` implements `Client\ClientInterface`, backed by Guzzle, in the same shape as `AnthropicClient`.
- `fromEnvironment()` reads `OPENAI_API_KEY` and throws before any HTTP call if missing.
- Model is pinned as a `public const string` (a current OpenAI tool-calling-capable model) so swaps are a one-line edit.
- The adapter translates OpenAI's tool-call request/response shapes to whatever generic shape `ClientInterface` returns. `AgentLoop` does not learn that there are two providers.
- If the current `ClientInterface` shape is Anthropic-leaky, redesign it as part of this story. Document the shape decision in `docs/system.md` under the agent-loop section (or a new entry).
- Tests cover the adapter with hand-rolled fake HTTP responses, mirroring the `AnthropicClient` test pattern. No live API calls in CI.
- `composer check` is green.
