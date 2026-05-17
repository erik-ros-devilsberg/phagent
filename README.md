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

## Providers

phagent ships two `ClientInterface` adapters:

- `Phagent\Client\AnthropicClient` — Anthropic Messages API.
- `Phagent\Client\OpenAIClient` — OpenAI Chat Completions API, with a configurable `$baseUrl` (constructor arg or `OPENAI_BASE_URL` env var).

Because `OpenAIClient`'s base URL is configurable, the same adapter drives every OpenAI-compatible endpoint — no second adapter required. Examples:

| Endpoint           | `OPENAI_BASE_URL`                                                        | Notes                                                                  |
| ------------------ | ------------------------------------------------------------------------ | ---------------------------------------------------------------------- |
| Ollama (local)     | `http://localhost:11434/v1/chat/completions`                             | Any open-weight model you've `ollama pull`'d (Qwen, Llama, Gemma, …).  |
| Groq               | `https://api.groq.com/openai/v1/chat/completions`                        | Hosted, fast inference for Llama, Mixtral, Gemma.                      |
| Together           | `https://api.together.xyz/v1/chat/completions`                           | Hosted open-weight catalogue.                                          |
| OpenRouter         | `https://openrouter.ai/api/v1/chat/completions`                          | Aggregator over many providers.                                        |
| DashScope (Qwen)   | `https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions` | Alibaba's OpenAI-compatibility mode for Qwen.                          |
| vLLM / llama.cpp   | your server URL                                                          | Self-hosted OpenAI-compatible servers.                                 |

Set `OPENAI_API_KEY` (any non-empty string for keyless local servers like Ollama) and `OPENAI_BASE_URL`, then construct `OpenAIClient::fromEnvironment()` — the rest of the stack is unchanged.

## Examples

Two runnable examples live in `examples/`. Both load `.env` automatically.

### `examples/run.php` — multi-turn tool loop

Uses `AnthropicClient::fromEnvironment()` (Guzzle PSR-18 fallback) and registers `GetCurrentTimeTool`. The model decides to call `get_current_time`, the loop dispatches it, feeds the result back, and the model produces a final answer.

```sh
php examples/run.php "what time is it"
echo "what time is it" | php examples/run.php
```

### `examples/run-summarise.php` — single-turn summariser

Uses `Symfony\Component\HttpClient\Psr18Client` wired explicitly into all three `AnthropicClient` constructor slots (no Guzzle autoloaded), passes an empty `ToolRegistry`, and shapes behaviour via a `$systemPrompt`. Demonstrates two things at once: the PSR-18 port is real (swap to any compliant client), and the canonical task-specific-agent pattern from `CLAUDE.md` (no tools, system prompt drives the agent).

```sh
php examples/run-summarise.php "Long text you want summarised in one sentence."
php examples/run-summarise.php < README.md
```

### Log verbosity

By default both examples print only the final result. Set `PHAGENT_LOG_LEVEL` to a PSR-3 level (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`) to enable per-turn logging on stdout and a `[turns=N stop_reason=…]` summary on stderr.

```sh
PHAGENT_LOG_LEVEL=info  php examples/run.php "what time is it"   # boundaries only
PHAGENT_LOG_LEVEL=debug php examples/run.php "what time is it"   # includes tool-call payloads
```

## Layout

- `src/` — library code (`Phagent\` namespace)
- `tests/` — PHPUnit tests (`Phagent\Tests\` namespace)
- `docs/` — agile workflow artefacts (sprints, user stories, system decisions)
