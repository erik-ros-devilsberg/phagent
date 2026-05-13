# phagent

A simple AI agent harness written in PHP.

## Requirements

- PHP `^8.4`
- [Composer](https://getcomposer.org/)

## Install

```sh
composer install
```

## Scripts

| Command             | What it does                                                     |
| ------------------- | ---------------------------------------------------------------- |
| `composer test`     | Run the PHPUnit test suite.                                      |
| `composer lint`     | Check code style with PHP-CS-Fixer (dry run, exits non-zero).    |
| `composer fix`      | Apply PHP-CS-Fixer fixes in place.                               |
| `composer analyse`  | Run PHPStan static analysis at level 8.                          |
| `composer check`    | Run `lint` + `analyse` + `test` as a single pre-commit gate.     |

### Running a single test

```sh
vendor/bin/phpunit --filter <TestName>
```

For example, `vendor/bin/phpunit --filter testNamespaceAutoloads`.

## Layout

- `src/` — library code (`Phagent\` namespace)
- `tests/` — PHPUnit tests (`Phagent\Tests\` namespace)
- `docs/` — agile workflow artefacts (sprints, user stories, system decisions)
