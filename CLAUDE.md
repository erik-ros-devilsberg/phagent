# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`phagent` is a simple AI agent harness written in PHP 8.4. The agent loop (Anthropic Messages API, multi-turn tool-use) is still ahead — see [`docs/user-stories/backlog/02-agent-loop.md`](docs/user-stories/backlog/02-agent-loop.md).

## Stack

- **PHP** `^8.4`
- **HTTP client** Guzzle (`guzzlehttp/guzzle`)
- **Tests** PHPUnit 11
- **Static analysis** PHPStan level 8
- **Code style** PHP-CS-Fixer (`@PSR12` + `@PHP84Migration`, `declare(strict_types=1)` enforced)

## Layout

- `src/` — library code, `Phagent\` namespace
- `tests/` — PHPUnit tests, `Phagent\Tests\` namespace
- `bin/` — CLI entry points (added by the agent-loop sprint)
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
- Developer writes tests first (red phase), then implements (green phase) — never skip the red phase
- Review is optional and ad-hoc — trigger it with `/agile:review` when you want it
- Defects found in review are either fixed immediately or become new user stories

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
