---
story: Repo Organisation
created: 2026-05-13
---

## Description

Establish the project skeleton before any feature code lands. This story sets directory layout, Composer setup, autoloading, testing, and code-quality tooling so every later story builds on the same foundation.

Proposed defaults (confirm during shaping):

- **PHP version** — `^8.4` (property hooks, asymmetric visibility, `#[\Override]`).
- **Namespace** — `Phagent\` mapped via PSR-4 to `src/`.
- **HTTP client** — Guzzle (`guzzlehttp/guzzle`), already chosen.
- **Tests** — PHPUnit 11, tests live in `tests/` mirroring `src/`.
- **Static analysis** — PHPStan at level `8` (strong default; ratchet to 9 or max once type boundaries settle).
- **Code style** — PHP-CS-Fixer with the PSR-12 ruleset plus `@PHP84Migration`.
- **Composer scripts** — `composer test`, `composer lint`, `composer fix`, `composer check` (runs lint + analyse + test).

Directory layout:

```
phagent/
├── bin/            CLI entry points (e.g. bin/phagent)
├── src/            library code (Phagent\ namespace)
├── tests/          PHPUnit tests (Phagent\Tests\ namespace)
├── config/         config templates / .env.example (if needed)
├── docs/           agile docs (already present)
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.php
├── .gitignore
├── .editorconfig
└── README.md
```

## Acceptance Criteria

- `composer.json` exists with PSR-4 autoload mapping `Phagent\` → `src/` and `Phagent\Tests\` → `tests/`.
- `composer install` produces a working `vendor/` with Guzzle, PHPUnit, PHPStan, and PHP-CS-Fixer pinned to compatible versions.
- `composer test` runs PHPUnit; a placeholder test passes to prove the wiring.
- `composer lint` runs PHP-CS-Fixer in `--dry-run` mode and exits non-zero on violations.
- `composer fix` runs PHP-CS-Fixer in write mode.
- `composer analyse` runs PHPStan at the configured level and passes on the empty skeleton.
- `composer check` chains lint + analyse + test as a single pre-commit gate.
- `.gitignore` excludes `vendor/`, `.phpunit.cache/`, `.php-cs-fixer.cache`, `.phpstan.cache/`, and `.env`.
- `.editorconfig` enforces 4-space indent for PHP, LF line endings, final newline, trim trailing whitespace.
- `README.md` documents: minimum PHP version, install command, the four composer scripts, and how to run a single test (`vendor/bin/phpunit --filter <name>`).
- `CLAUDE.md` "Working Notes" section is updated with the chosen commands so future sessions don't have to rediscover them.
