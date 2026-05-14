# Resume Notes

A handoff file for the next session. `CLAUDE.md` is the project's stable spec; this file is the volatile "where we left off" and the working context the user shared verbally.

## Where we are

Three sprints done. The kernel is a small library that:

- Runs a multi-turn Anthropic tool-use loop (`AgentLoop`).
- Takes a per-call **system prompt** and a per-instance **model**, **`max_tokens`**, **`max_turns`**.
- Returns a PHP-native `AgentResult` (`text`, `stopReason`, `turns`).
- Logs via `Psr\Log\LoggerInterface`.

Read `docs/system.md` for the cumulative architecture, then `CLAUDE.md` for the wedge and the drift rules. Don't restate the wedge — internalize it.

## What's done

- **01 — Project skeleton** (`docs/user-stories/done/01-repo-organisation.md`)
- **02 — Agent loop** (`docs/user-stories/done/02-agent-loop.md`)
- **03 — PSR-3 logging** (`docs/user-stories/done/03-psr3-logging.md`)
- **05 — Agent configuration + `AgentResult`** (`docs/user-stories/done/05-agent-configuration.md`)

Numbering gap at 04 is intentional — see below.

## Dangling

- `docs/user-stories/backlog/04-openai-provider.md` and `docs/sprints/04-openai-provider.md` are both still on disk. The user explicitly said they don't want OpenAI work right now. Don't shape, don't execute. Either leave them as a future option, or ask the user whether to delete on resume.

## Target use case (verbally shared, not in repo docs)

The user runs **1m.news** (a Laravel app at `/home/erik/git/one-minute-code/one-minute-news/`). It currently invokes Claude Code via subprocess for three agents:

1. **StorySeedPicker** — model `claude-haiku-4-5-20251001`, no tools, ~2 turns. Output: `{chosen_id: int, reason: string}`.
2. **StoryResearcher** — model `claude-sonnet-4-6`, tools `WebSearch` + `WebFetch`, ~15 turns. Output: research bundle.
3. **StoryWriter** — model `claude-sonnet-4-6`, no tools, ~2 turns. Output: `{title, content, tags}`.

Each agent has its system prompt in a markdown file (`docs/agents/{seed-picker,researcher,writer}.md`) and stores results in a `Draft` Eloquent model. They use Anthropic's `--json-schema` to enforce structured output.

phagent's purpose is to replace those subprocess invocations with a `composer require`d library call. After sprint 05, the kernel can express the **picker** and **writer** shapes if the caller is willing to prompt for JSON and `json_decode` themselves. The **researcher** still can't be implemented because it needs real tools.

## Next priorities (in order of how load-bearing they are)

1. **Structured output enforcement.** Today the caller prompts for JSON and parses; the model can return malformed JSON. Two paths to evaluate during shaping:
   - Anthropic's `response_format` / JSON-schema field on the request (closest mirror to what 1m.news already gets via `--json-schema`).
   - A "designated terminal tool" the embedder registers — when the model calls it, the loop intercepts and returns its input as structured data.
   Probably the response_format path is simpler and matches existing intuition.
2. **Usage metadata on `AgentResult`.** Anthropic returns `usage` (input/output tokens). Currently discarded. Adding `inputTokens` / `outputTokens` / `model` fields to `AgentResult` unlocks per-call cost tracking, which 1m.news already records.
3. **Per-run tool swap.** The `ToolRegistry` is fixed at construction. 1m.news uses different tools per agent — fine as long as you build one `AgentLoop` per agent. But if a future story wants different tools per call, that's a kernel change.
4. **`fromEnvironment()` model/tokens overrides.** Cosmetic; constructor already accepts them.

Real tools (`WebSearch`, `WebFetch`, file IO, shell) are **not** kernel concerns. They live in a separate package (probably `phagent/tools-web` or in the embedder app). See the CLAUDE.md drift list.

## Working style — what the user has signalled

- **Brief responses, no sprawl.** Sharp, direct. State the decision, not the deliberation. End-of-turn summaries are one or two sentences.
- **No scope creep in sprints.** Resist speculative fields, "while we're here" cleanups, or backwards-compat shims.
- **Pre-1.0, no external consumers.** Breaking changes are fine; don't add deprecation paths.
- **The user shapes too.** They edit `CLAUDE.md` directly between turns. Re-read the file after they push back — they've usually trimmed your prose.
- **The user owns the backlog.** When you write stories, frame them as drafts the user can revise. Don't proliferate speculative backlog items.

## Pointers

- Wedge / positioning / drift signals: `CLAUDE.md` (top section).
- Architecture history: `docs/system.md`.
- Sprint workflow: `CLAUDE.md` → "Agile Workflow".
- 1m.news real code (for grounding): `/home/erik/git/one-minute-code/one-minute-news/app/Services/Agentic/`.
