<?php

declare(strict_types=1);

namespace Phagent;

interface Logger
{
    public function log(int $turn, string $role, string $text): void;
}
