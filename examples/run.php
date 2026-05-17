<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/StdoutLogger.php';

Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

use Phagent\AgentLoop;
use Phagent\Client\AnthropicClient;
use Phagent\Examples\StdoutLogger;
use Phagent\Tool\GetCurrentTimeTool;
use Phagent\Tool\ToolRegistry;

$prompt = $argv[1] ?? null;
if (!is_string($prompt) || trim($prompt) === '') {
    $stdin = stream_get_contents(STDIN);
    $prompt = is_string($stdin) ? trim($stdin) : '';
}

if ($prompt === '') {
    fwrite(STDERR, "Usage: php examples/run.php <prompt>\n  or:  echo <prompt> | php examples/run.php\n");
    exit(1);
}

try {
    $client = AnthropicClient::fromEnvironment();
} catch (\RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$registry = new ToolRegistry();
$registry->register(new GetCurrentTimeTool());

$logLevel = getenv('PHAGENT_LOG_LEVEL');
$logger = is_string($logLevel) && $logLevel !== '' ? new StdoutLogger($logLevel) : null;

$loop = new AgentLoop($client, $registry, $logger);
$result = $loop->run($prompt);

echo $result->text, "\n";
if ($logger !== null) {
    fwrite(STDERR, sprintf("[turns=%d stop_reason=%s]\n", $result->turns, $result->stopReason));
}
