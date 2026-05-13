<?php

declare(strict_types=1);

namespace Phagent\Tool;

interface Tool
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): string;
}
