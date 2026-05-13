---
story: Agent Loop
created: 2026-05-13
---

## Description

Build the core agent loop that drives an LLM through a multi-turn tool-use cycle until it returns a final answer. This is the heart of the harness — every later feature (tools, memory, transcripts, UI) hangs off this loop.

Assumes [[01-repo-organisation]] is complete (Composer, PSR-4 autoloading, PHPUnit, PHPStan, PHP-CS-Fixer, Guzzle).

Open decisions for shaping:

- **LLM provider** — Anthropic Messages API is the default assumption; confirm during shaping.
- **Tool registry** — how tools declare their JSON schema and how the loop dispatches a tool call to PHP code.

The first version should be deliberately minimal: one entry-point script, a single hardcoded tool (e.g. `get_current_time`) to prove the dispatch path works, and stdout logging of each turn. No persistence, no UI, no streaming.

## Acceptance Criteria

- A PHP entry point accepts a user prompt (CLI arg or stdin) and prints the assistant's final text answer.
- The loop sends the prompt to the LLM with the registered tool(s) declared.
- When the LLM returns a tool-use response, the harness executes the matching PHP handler and feeds the result back into the next turn.
- The loop terminates when the LLM returns a stop reason indicating no further tool calls (final text response).
- At least one demonstration tool is registered and invokable end-to-end (e.g. `get_current_time` returns the current timestamp).
- Each turn (user → assistant → tool result) is logged to stdout in a human-readable form so the cycle is observable.
- A hard iteration cap (e.g. 10 turns) prevents runaway loops; exceeding it raises an error.
- The API key is read from an environment variable, not hardcoded.
- Tests cover: a no-tool-call path (LLM answers directly) and a single-tool-call path (LLM calls the demo tool, then answers). The LLM call itself is stubbed/mocked — tests must not hit a live API.
