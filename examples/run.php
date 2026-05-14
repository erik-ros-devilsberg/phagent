<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/StdoutLogger.php';

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

$loop = new AgentLoop($client, $registry, new StdoutLogger());
$answer = $loop->run($prompt);

echo $answer, "\n";
