---
story: Ollama provider adapter (local open-weight models)
created: 2026-05-17
---

## Description

`AnthropicClient` and (planned) `OpenAIClient` (sprint 04) both target hosted, closed-weight providers. The kernel's **provider-agnostic** promise is incomplete until it can drive an open-weight model running locally. The natural fit is [Ollama](https://ollama.com), the dominant local inference server in 2026: one binary, an HTTP API on `localhost:11434`, native tool-calling support since v0.3, and a model catalogue that covers Gemma 3, Llama 3.x, Qwen 2.5, Mistral, Phi, and dozens more.

Adding an `OllamaClient` proves three things at once:

1. The PSR-18 port from sprint 06 is real — `OllamaClient` consumes the same PSR contracts.
2. `ClientInterface`'s return shape (assumed to be neutralised in sprint 04 alongside the OpenAI adapter) is genuinely portable, not Anthropic-shaped.
3. phagent can run **fully offline** against a model the consumer controls — no API key, no per-token cost, no network egress. That's a meaningful differentiator for compliance-bound consumers.

Ollama's wire format is distinct from OpenAI's (different endpoint, different request/response shapes for `tools` and `tool_calls`), so this is a new adapter, not a base-URL tweak on `OpenAIClient`.

## Acceptance Criteria

- `Client\OllamaClient` implements `Client\ClientInterface`, constructed with the same PSR-18 + PSR-17 trio as `AnthropicClient` (no `GuzzleHttp\*` types in `src/`).
- Default endpoint is `http://localhost:11434/api/chat`, overridable via constructor (`string $baseUrl = self::DEFAULT_BASE_URL`). No API key required by default; an optional bearer header is supported for hosted Ollama deployments behind a reverse proxy.
- Model is pinned as a `public const string DEFAULT_MODEL` (pick a current tool-call-capable open-weight model at shape time — candidates: `llama3.1`, `qwen2.5`, `gemma3:27b`). Overridable via constructor, same pattern as `AnthropicClient`.
- `OllamaClient::fromEnvironment()` factory mirrors `AnthropicClient::fromEnvironment()`'s ergonomics: reads `OLLAMA_BASE_URL` (optional, defaults to localhost) and `OLLAMA_API_KEY` (optional), falls back to the guarded Guzzle PSR-18 stack when no client/factories are passed.
- The adapter translates the neutral message shape (as established by sprint 04's redesign) → Ollama's request format:
  - `role: user|assistant` maps directly.
  - `tool_use` block → assistant message with `tool_calls: [{function: {name, arguments}}]` array.
  - `tool_result` block → separate `role: tool` message with the result content.
  - Tool schemas → Ollama `tools: [{type: 'function', function: {name, description, parameters}}]`.
- The adapter translates Ollama's response → the neutral return shape:
  - Response `message.tool_calls` present → `stop_reason: tool_use` with each call rendered as a `['type' => 'tool_use', 'id' => ..., 'name' => ..., 'input' => [...]]` block. (Note: Ollama responses do not carry a tool-call `id`; synthesise one — UUID v4 or `call_<n>` — and round-trip it consistently.)
  - Otherwise `stop_reason: end_turn` with a single `['type' => 'text', 'text' => ...]` block.
- Tests cover the adapter with a hand-rolled PSR-18 fake (same pattern as the post-sprint-06 `AnthropicClientTest`). No live Ollama calls in CI.
  - Happy path: fake returns a text-only response → adapter returns neutral end_turn shape.
  - Tool-call path: fake returns a `message.tool_calls` payload → adapter returns neutral tool_use shape with a synthesised id.
  - Round-trip: outgoing request body for a message list containing a `tool_result` block serialises to Ollama's `role: tool` shape.
  - `fromEnvironment()` smoke test without HTTP call.
- `examples/run-ollama.php` runs end-to-end against a local Ollama server using the `get_current_time` tool and the same prompt as `examples/run.php`. Documented as requiring `ollama serve` and the chosen model pulled (`ollama pull <model>`).
- `composer check` is green.
- New entry in `docs/system.md` documenting the adapter, the synthesised-id decision, and how this lands the offline-capable agent.

## Dependencies and ordering

- **Sprint 04 (`OpenAIClient`) must ship first.** Its `ClientInterface` redesign establishes the neutral return shape this story translates Ollama into. Building `OllamaClient` against the current Anthropic-shaped interface would lock the Anthropic shape in further.

## Out of scope

- Streaming responses. Ollama supports `stream: true` and SSE; phagent's `ClientInterface` is currently request/response only. Add streaming as a kernel-wide story when there's a use case.
- Multimodal inputs (images). Ollama supports them; the kernel does not yet.
- Automatic model pulling. `OllamaClient` assumes the model is already pulled; surfacing the "model not found" error clearly is enough.
- Embeddings (`/api/embeddings`). Different concern, different story.
