<?php

declare(strict_types=1);

namespace Phagent\Tests;

use Phagent\Tool\Tool;
use Phagent\Tool\ToolRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolRegistry::class)]
final class ToolRegistryTest extends TestCase
{
    public function testRegistersAndRetrievesTool(): void
    {
        $registry = new ToolRegistry();
        $tool = $this->makeTool('weather', 'Returns weather.');

        $registry->register($tool);

        self::assertSame($tool, $registry->get('weather'));
    }

    public function testGetThrowsWhenToolUnknown(): void
    {
        $registry = new ToolRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('missing');
    }

    public function testAllSchemasReturnsApiShape(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->makeTool('alpha', 'A'));
        $registry->register($this->makeTool('beta', 'B'));

        $schemas = $registry->allSchemas();

        self::assertCount(2, $schemas);
        self::assertSame('alpha', $schemas[0]['name']);
        self::assertSame('A', $schemas[0]['description']);
        self::assertSame(['type' => 'object'], $schemas[0]['input_schema']);
        self::assertSame('beta', $schemas[1]['name']);
    }

    private function makeTool(string $name, string $description): Tool
    {
        return new class ($name, $description) implements Tool {
            public function __construct(
                private readonly string $toolName,
                private readonly string $toolDescription,
            ) {
            }

            public function name(): string
            {
                return $this->toolName;
            }

            public function description(): string
            {
                return $this->toolDescription;
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function execute(array $input): string
            {
                return '';
            }
        };
    }
}
