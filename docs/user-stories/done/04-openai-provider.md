---
story: OpenAI provider adapter
created: 2026-05-14
---

## Description

`Client\AnthropicClient` is currently the only implementation of `Client\ClientInterface`. The "provider-agnostic kernel" promise in `CLAUDE.md` is theoretical until a second adapter exists. Adding one forces the port to actually be a port — any leak of Anthropic-specific shapes (e.g. content-block arrays, stop-reason strings) surfaces immediately and gets fixed at the interface level.

Add an OpenAI Chat Completions adapter that speaks the same `ClientInterface` so `AgentLoop` runs unchanged against either provider.

## Acceptance Criteria

- `Client\OpenAIClient` implements `Client\ClientInterface`, constructed with the PSR-18 + PSR-17 trio established in sprint 06 (no `GuzzleHttp\*` types in `src/`).
- `fromEnvironment()` reads `OPENAI_API_KEY` and throws before any HTTP call if missing. The factory mirrors `AnthropicClient::fromEnvironment()`'s guarded Guzzle fallback for the PSR-18 stack.
- Model is pinned as a `public const string` (a current OpenAI tool-calling-capable model) so swaps are a one-line edit.
- The adapter translates OpenAI's tool-call request/response shapes to whatever generic shape `ClientInterface` returns. `AgentLoop` does not learn that there are two providers.
- If the current `ClientInterface` shape is Anthropic-leaky, redesign it as part of this story. Document the shape decision in `docs/system.md` under the agent-loop section (or a new entry).
- **Configurable base URL.** Constructor takes `string $baseUrl = self::DEFAULT_BASE_URL` (default: OpenAI's `https://api.openai.com/v1/chat/completions`). `fromEnvironment()` honours `OPENAI_BASE_URL` if set. This unlocks every OpenAI-compatible endpoint without a new adapter: Alibaba DashScope's OpenAI mode (Qwen), Groq, Together, OpenRouter, Fireworks, vLLM, llama.cpp's OpenAI server, Ollama's `/v1/chat/completions` shim, etc.
- A short note in `docs/system.md` (or README) listing two or three OpenAI-compatible endpoints the adapter has been smoke-tested against, so consumers know the base-URL path is real and not just theoretical.
- Tests cover the adapter with hand-rolled fake HTTP responses, mirroring the post-sprint-06 `AnthropicClientTest` pattern (PSR-18 fake, not Guzzle MockHandler). No live API calls in CI. A dedicated test asserts that a non-default `$baseUrl` is honoured on the outgoing PSR-7 request URI.
- `composer check` is green.
