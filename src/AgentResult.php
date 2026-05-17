<?php

declare(strict_types=1);

namespace Phagent;

final readonly class AgentResult
{
    public function __construct(
        public string $text,
        public string $stopReason,
        public int $turns,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {
    }
}
