<?php

declare(strict_types=1);

namespace Phagent\Tool;

final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): Tool
    {
        if (!isset($this->tools[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown tool: %s', $name));
        }

        return $this->tools[$name];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allSchemas(): array
    {
        $schemas = [];
        foreach ($this->tools as $tool) {
            $schemas[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ];
        }

        return $schemas;
    }
}
