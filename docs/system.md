# System Documentation

This file is maintained by `/agile:wrap-sprint`. Each section describes one completed sprint — what was built, why, and key decisions made. Read this to understand the system without reading all the code.

## Project Skeleton (2026-05-13)

**Stories:** [01-repo-organisation](user-stories/done/01-repo-organisation.md)

Established the PHP project foundation. Every later sprint builds on this.

### What was built

- `composer.json` — `php: ^8.4`, requires `guzzlehttp/guzzle:^7.9`; dev-requires `phpunit/phpunit:^11.4`, `phpstan/phpstan:^2.0`, `friendsofphp/php-cs-fixer:^3.64`.
- PSR-4 autoload: `Phagent\` → `src/`, `Phagent\Tests\` → `tests/`.
- `phpunit.xml` — strict config (`failOnWarning`, `failOnRisky`, `failOnNotice`, `failOnDeprecation`, `beStrictAboutOutputDuringTests`, `beStrictAboutChangesToGlobalState`), random execution order, `tests/` suite, source coverage scoped to `src/`.
- `phpstan.neon` — level 8 over `src/` and `tests/`.
- `.php-cs-fixer.php` — `@PSR12` + `@PHP84Migration` + `declare_strict_types` (risky rules allowed).
- Composer scripts: `test`, `lint`, `fix`, `analyse`, `check` (the last chains lint + analyse + test).
- `.gitignore`, `.editorconfig` (4-space LF), `README.md` documenting install, scripts, and single-test invocation.
- Placeholder `src/Phagent.php` and `tests/SmokeTest.php` to prove autoload + test wiring.
- `CLAUDE.md` "Working Notes" replaced with concrete Stack/Layout/Commands sections.

### Key decisions

- **PHPStan level 8, not max.** Level 9/max enforces "no `mixed` anywhere" and strict array shapes, which would create unnecessary friction at HTTP and JSON boundaries before the codebase exists. Ratchet later, once type boundaries settle.
- **PHP-CS-Fixer over PHP_CodeSniffer.** More active project, better PSR-12 + PHP-version-migration ruleset story.
- **PHPStan 2.x** (installed 2.1.54) — the current major. Config file uses the 2.x format.
- **`@PHP84Migration` ruleset** keeps style in step with the language version floor.
- **Strict PHPUnit defaults from day one** — `failOnDeprecation`, etc. Cheaper to enforce now than retrofit.

### Verification

`composer check` exits zero on the empty skeleton: PHP-CS-Fixer clean, PHPStan level 8 OK, PHPUnit 1/1 passing.

### Deferred / scope notes

- `bin/phagent` CLI entry point — deferred to the agent-loop sprint where it gains real behaviour.
- `config/` directory — skipped; create when the first config file actually lands.
- Git initialisation — explicitly skipped by user direction at the end of execution.

