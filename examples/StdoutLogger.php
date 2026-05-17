<?php

declare(strict_types=1);

namespace Phagent\Examples;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

final class StdoutLogger extends AbstractLogger
{
    /**
     * PSR-3 levels ordered from most verbose (lowest threshold) to least verbose.
     */
    private const array LEVEL_ORDER = [
        LogLevel::DEBUG     => 0,
        LogLevel::INFO      => 1,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 3,
        LogLevel::ERROR     => 4,
        LogLevel::CRITICAL  => 5,
        LogLevel::ALERT     => 6,
        LogLevel::EMERGENCY => 7,
    ];

    private readonly int $minLevel;

    public function __construct(string $minLevel = LogLevel::DEBUG)
    {
        if (!isset(self::LEVEL_ORDER[$minLevel])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid log level "%s". Valid: %s.',
                $minLevel,
                implode(', ', array_keys(self::LEVEL_ORDER)),
            ));
        }
        $this->minLevel = self::LEVEL_ORDER[$minLevel];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelName = is_string($level) ? $level : 'log';
        $rank = self::LEVEL_ORDER[$levelName] ?? null;
        if ($rank === null || $rank < $this->minLevel) {
            return;
        }

        $line = sprintf('[%s] %s', $levelName, (string) $message);
        if ($context !== []) {
            $line .= ' ' . (string) json_encode(
                $context,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }
        fwrite(STDOUT, $line . "\n");
    }
}
