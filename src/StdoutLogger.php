<?php

declare(strict_types=1);

namespace Phagent;

final class StdoutLogger implements Logger
{
    public function log(int $turn, string $role, string $text): void
    {
        fwrite(STDOUT, sprintf("[turn %d] %s: %s\n", $turn, $role, $text));
    }
}
