## Project

`phagent` is a minimal, framework-agnostic AI agent harness in PHP 8.4. See [`docs/system.md`](docs/system.md) for cumulative architecture.

The kernel makes these promises:

- **Library-first.** Embeddable in a Laravel queue job, a Symfony controller, a plain PHP script, a CLI.
- **Prompt in, result out.** One call gives a prompt (which specifies the desired output shape) and gets back the final result. The kernel runs the agentic loop — tool calls, file ops, multiple turns — internally. It persists nothing across calls.
- **No framework coupling in the kernel.** No Laravel service provider, no Symfony bundle, no opinions about routing, queues, or the request lifecycle. Those belong in adapter packages on top.
- **Provider-agnostic.** `Client\ClientInterface` is the port. Adapters (Anthropic, OpenAI, …) are thin and live behind it.
- **PSR-friendly.** PSR-3 for logging, PSR-11 for container interop, where they fit. Don't reinvent infrastructure that already has a PSR.
- **No coding-agent identity in the kernel.** Read/Edit/Bash/Web-style tools — if and when they ship — live in a separate package (e.g. `phagent/coding-tools`), never in `src/`. The same kernel must serve a content-moderation agent and a code-editing agent equally well.

### Drift signals — things that don't belong in `src/`

- `Session` / `Transcript` / `History` types
- Slash commands, a TUI, plan mode, permission prompts, any human-in-the-loop affordance
- Laravel- or Symfony-specific dependencies
- Tools that assume a coding-agent use case (file IO, shell, browser)
- A hardcoded system prompt at the kernel level

When in doubt: would this make sense for an agent that summarises news articles, runs unattended in a background queue, and never talks to a human? If no, it belongs in a separate package.

## Stack

- **PHP** `^8.4`
- **HTTP client** Guzzle (`guzzlehttp/guzzle`)
- **Tests** PHPUnit 11
- **Static analysis** PHPStan level 8
- **Code style** PHP-CS-Fixer (`@PSR12` + `@PHP84Migration`, `declare(strict_types=1)` enforced)

## Layout

- `src/` — library code, `Phagent\` namespace
- `tests/` — PHPUnit tests, `Phagent\Tests\` namespace
- `examples/` — runnable usage examples (not part of the library surface)
- `docs/` — agile artefacts (sprints, stories, decisions)

## Commands

| Command             | What it does                                                |
| ------------------- | ----------------------------------------------------------- |
| `composer install`  | Install dependencies.                                       |
| `composer test`     | Run PHPUnit.                                                |
| `composer lint`     | PHP-CS-Fixer dry-run (non-zero on violations).              |
| `composer fix`      | PHP-CS-Fixer write mode.                                    |
| `composer analyse`  | PHPStan at the configured level.                            |
| `composer check`    | `lint` + `analyse` + `test` — the pre-commit gate.          |

Run a single test: `vendor/bin/phpunit --filter <TestName>`.

Always run `composer check` before declaring a task done.

## Agile Workflow

This project uses the agile plugin. Follow these rules when building features.

### Flow

```
1. Human writes user stories to docs/user-stories/backlog/
2. /agile:shape <story-slug> [<story-slug2> ...]
        → product-manager reads stories and shapes a sprint plan → saved to docs/sprints/
        STOP: human reviews and approves plan
3. /agile:execute docs/sprints/<sprint-slug>.md
        → developer implements (TDD: tests first, then implement)
        STOP: human reviews the work
4. /agile:review (optional, ad-hoc)
        → reviewer reports findings inline
        → human fixes defects now or creates new user stories
5. /agile:wrap-sprint
        → documents sprint in docs/system.md
        → moves user stories to docs/user-stories/done/
        → deletes sprint plan
6. /agile:commit → commit and push
```

### Rules

- Never start building without an approved sprint plan in `docs/sprints/`
- Sprint plans are the single source of truth for the sprint — update them as execution progresses
- Developer writes tests first, then implements — never skip writing tests
- Review is user invoked — trigger it with `/agile:review`
- Defects found in review become new user stories

### Directory structure

- `docs/user-stories/backlog/` — pending user stories (human-written)
- `docs/user-stories/done/` — completed user stories (moved here by `/agile:wrap-sprint`)
- `docs/sprints/` — active sprint plans (deleted after `/agile:wrap-sprint`)
- `docs/system.md` — cumulative decisions and outcomes

### User story format

File naming: `NN-story-name.md` — use a two-digit number prefix to control ordering (e.g. `01-user-authentication.md`, `02-password-reset.md`).

```markdown
---
story: <Story Name>
created: YYYY-MM-DD
---

## Description

<What needs to be built and why>

## Acceptance Criteria

- <criterion 1 — specific and testable>
- <criterion 2>
```

### Human gates

1. After `/agile:shape` — approve the sprint plan before executing
2. After `/agile:execute` — review the work and decide whether to run `/agile:review`
