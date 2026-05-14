<?php

declare(strict_types=1);

namespace Phagent\Examples;

use Psr\Log\AbstractLogger;
use Stringable;

final class StdoutLogger extends AbstractLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $line = sprintf('[%s] %s', is_string($level) ? $level : 'log', (string) $message);
        if ($context !== []) {
            $line .= ' ' . (string) json_encode(
                $context,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }
        fwrite(STDOUT, $line . "\n");
    }
}
