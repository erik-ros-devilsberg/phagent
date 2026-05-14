---
story: PSR-3 logging
created: 2026-05-14
---

## Description

The current `Phagent\Logger` interface is project-internal. Replace it with `Psr\Log\LoggerInterface` so the kernel speaks the same logging contract as the rest of the PHP ecosystem.

This is a small change but it's load-bearing for the framework-agnostic positioning declared in `CLAUDE.md` — an embedding application (Laravel, Symfony, Slim, plain PHP) passes its own PSR-3 logger in; phagent doesn't impose one or ask the embedder to learn a custom interface.

## Acceptance Criteria

- `psr/log` `^3` is added to `composer.json` `require`.
- `AgentLoop` accepts a `Psr\Log\LoggerInterface` (or `null`) instead of `Phagent\Logger`.
- Per-turn log messages map to PSR-3 levels — `info` for normal turn boundaries, `debug` for verbose detail (tool input/output payloads).
- `Phagent\Logger` and `Phagent\StdoutLogger` are deleted; no backwards-compat shim.
- `examples/run.php` wires a concrete PSR-3 logger. Either pull in a small dependency (e.g. a stdout-targeted PSR-3 implementation) or ship a ~20-line in-tree implementation if no clean option exists — decide during shaping.
- Existing tests still pass with `null` as the logger argument.
- `composer check` is green.
