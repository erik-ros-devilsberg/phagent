# Sprint Status

Maintained by the agile plugin. One row per sprint — updated by `/agile:shape`, `/agile:execute`.

| Sprint | Slug | Status | Description |
|--------|------|--------|-------------|
| Project Skeleton | project-skeleton | done | PHP 8.4 Composer scaffold with PHPUnit, PHPStan level 8, PHP-CS-Fixer, Guzzle. |
| Agent Loop | agent-loop | done | Anthropic Messages multi-turn tool-use loop with CLI entry and a demo time tool. |
| PSR-18 HTTP client port — move Guzzle to dev | 06-psr18-http-client | done | Replace direct Guzzle coupling with PSR-18/17/7 interfaces and move Guzzle to require-dev. |
| OpenAI provider adapter | 04-openai-provider | done | Second `ClientInterface` adapter (OpenAI Chat Completions) with configurable base URL for any OpenAI-compatible endpoint. |
| Fix tool_use.input empty-object round-trip | 07-tool-use-empty-input-round-trip | planned | Normalise empty `tool_use.input` to JSON object in AnthropicClient outgoing payload. |
