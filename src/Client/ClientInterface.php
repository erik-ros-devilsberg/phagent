<?php

declare(strict_types=1);

namespace Phagent\Client;

/**
 * Port to a chat-completion provider.
 *
 * Implementations translate to and from the kernel's neutral internal protocol,
 * so {@see \Phagent\AgentLoop} runs unchanged against any provider.
 *
 * # Neutral protocol
 *
 * ## Input — `$messages`
 *
 * A list of turns. Each turn is `['role' => 'user'|'assistant', 'content' => string|list<block>]`.
 *
 * Blocks (when `content` is a list) take one of three shapes:
 * - `['type' => 'text', 'text' => string]`
 * - `['type' => 'tool_use', 'id' => string, 'name' => string, 'input' => array<string, mixed>]`
 * - `['type' => 'tool_result', 'tool_use_id' => string, 'content' => string]`
 *
 * The `id` on a `tool_use` block and the `tool_use_id` on the corresponding
 * `tool_result` block MUST match within a single conversation.
 *
 * ## Input — `$tools`
 *
 * A list of JSON-Schema-shaped tool definitions:
 * `['name' => string, 'description' => string, 'input_schema' => array<string, mixed>]`.
 *
 * ## Output — return value
 *
 * `['stop_reason' => string, 'content' => list<block>, 'usage' => array{input_tokens: int, output_tokens: int}]`
 * where:
 * - `stop_reason` is one of `'end_turn'`, `'tool_use'`, `'max_tokens'`
 *   (additional values MAY appear but the loop only acts on `'tool_use'`).
 * - `content` blocks are `['type' => 'text', 'text' => string]` or
 *   `['type' => 'tool_use', 'id' => string, 'name' => string, 'input' => array<string, mixed>]`.
 * - `usage` carries the per-call token counts. When the upstream response omits
 *   usage (e.g. errors, or providers that do not report it), the adapter MUST
 *   return `['input_tokens' => 0, 'output_tokens' => 0]` rather than throwing —
 *   the kernel relies on a guaranteed `usage` shape for cross-turn accumulation.
 *
 * The protocol intentionally mirrors Anthropic's block shape because that shape
 * is a superset of what other providers (OpenAI, Ollama, etc.) emit. Adapters
 * for other providers translate their native shapes to and from this protocol.
 */
interface ClientInterface
{
    /**
     * @param list<array<string, mixed>> $messages neutral message list — see class docblock
     * @param list<array<string, mixed>> $tools    neutral tool definition list — see class docblock
     *
     * @return array<string, mixed> neutral response shape — see class docblock
     */
    public function sendMessages(array $messages, array $tools, ?string $systemPrompt = null): array;
}
