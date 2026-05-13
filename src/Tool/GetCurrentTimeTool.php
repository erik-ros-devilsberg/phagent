<?php

declare(strict_types=1);

namespace Phagent\Tool;

final class GetCurrentTimeTool implements Tool
{
    public function name(): string
    {
        return 'get_current_time';
    }

    public function description(): string
    {
        return 'Returns the current Unix timestamp as a string.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function execute(array $input): string
    {
        return (string) time();
    }
}
