<?php

/**
 * Summariser example — demonstrates two things at once:
 *
 *   1. The PSR-18 port is real. A single `Symfony\Component\HttpClient\Psr18Client`
 *      instance drops into all three constructor slots of `AnthropicClient`.
 *      No Guzzle is autoloaded; swap the client for any PSR-18 implementation
 *      (Buzz, your framework's own, etc.) and phagent works unchanged.
 *
 *   2. The canonical task-specific-agent shape from CLAUDE.md — a `$systemPrompt`
 *      drives behaviour, no tools are registered, and the loop completes in one
 *      turn. Compare to `examples/run.php`, which exercises the multi-turn tool
 *      loop.
 *
 * Usage:
 *
 *   php examples/run-summarise.php "The text you want summarised."
 *   echo "The text you want summarised." | php examples/run-summarise.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/StdoutLogger.php';

Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

use Phagent\AgentLoop;
use Phagent\Client\AnthropicClient;
use Phagent\Examples\StdoutLogger;
use Phagent\Tool\ToolRegistry;
use Symfony\Component\HttpClient\Psr18Client;

$prompt = $argv[1] ?? null;
if (!is_string($prompt) || trim($prompt) === '') {
    $stdin = stream_get_contents(STDIN);
    $prompt = is_string($stdin) ? trim($stdin) : '';
}

if ($prompt === '') {
    fwrite(STDERR, "Usage: php examples/run-summarise.php <text>\n  or:  echo <text> | php examples/run-summarise.php\n");
    exit(1);
}

$apiKey = getenv('ANTHROPIC_API_KEY');
if (!is_string($apiKey) || $apiKey === '') {
    fwrite(STDERR, "ANTHROPIC_API_KEY environment variable is not set.\n");
    exit(1);
}

$http = new Psr18Client();
$client = new AnthropicClient($http, $http, $http, $apiKey);
$registry = new ToolRegistry();

$logLevel = getenv('PHAGENT_LOG_LEVEL');
$logger = is_string($logLevel) && $logLevel !== '' ? new StdoutLogger($logLevel) : null;

$loop = new AgentLoop($client, $registry, $logger);

$systemPrompt = 'You are a summariser. Read the user\'s text and respond with a single concise '
    . 'sentence that captures its main point. No preamble, no commentary, just the sentence.';

$result = $loop->run($prompt, $systemPrompt);

echo $result->text, "\n";
if ($logger !== null) {
    fwrite(STDERR, sprintf("[turns=%d stop_reason=%s]\n", $result->turns, $result->stopReason));
}
