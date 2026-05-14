<?php

declare(strict_types=1);

namespace Phagent\Tests;

use Phagent\AgentLoop;
use Phagent\Client\ClientInterface;
use Phagent\Exception\LoopLimitException;
use Phagent\Tool\Tool;
use Phagent\Tool\ToolRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

#[CoversClass(AgentLoop::class)]
final class AgentLoopTest extends TestCase
{
    public function testReturnsFinalTextWhenModelDoesNotCallTools(): void
    {
        $client = new class () implements ClientInterface {
            public function sendMessages(array $messages, array $tools): array
            {
                return [
                    'stop_reason' => 'end_turn',
                    'content' => [
                        ['type' => 'text', 'text' => 'hello world'],
                    ],
                ];
            }
        };

        $loop = new AgentLoop($client, new ToolRegistry());

        self::assertSame('hello world', $loop->run('hi'));
    }

    public function testExecutesToolAndFeedsResultBackToModel(): void
    {
        $client = new class () implements ClientInterface {
            public int $calls = 0;
            /** @var list<array<string, mixed>> */
            public array $lastMessages = [];

            public function sendMessages(array $messages, array $tools): array
            {
                $this->calls++;
                $this->lastMessages = $messages;

                if ($this->calls === 1) {
                    return [
                        'stop_reason' => 'tool_use',
                        'content' => [
                            [
                                'type' => 'tool_use',
                                'id' => 'toolu_test',
                                'name' => 'echo',
                                'input' => ['text' => 'ping'],
                            ],
                        ],
                    ];
                }

                return [
                    'stop_reason' => 'end_turn',
                    'content' => [
                        ['type' => 'text', 'text' => 'done: ping'],
                    ],
                ];
            }
        };

        $registry = new ToolRegistry();
        $registry->register(new class () implements Tool {
            public function name(): string
            {
                return 'echo';
            }

            public function description(): string
            {
                return 'Echoes back the provided text.';
            }

            public function inputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => ['text' => ['type' => 'string']],
                    'required' => ['text'],
                ];
            }

            public function execute(array $input): string
            {
                $text = $input['text'] ?? '';

                return is_string($text) ? $text : '';
            }
        });

        $loop = new AgentLoop($client, $registry);

        self::assertSame('done: ping', $loop->run('say ping'));
        self::assertSame(2, $client->calls);

        $lastMessage = $client->lastMessages[count($client->lastMessages) - 1];
        self::assertSame('user', $lastMessage['role']);
        self::assertIsArray($lastMessage['content']);
        $resultBlock = $lastMessage['content'][0];
        self::assertIsArray($resultBlock);
        self::assertSame('tool_result', $resultBlock['type']);
        self::assertSame('toolu_test', $resultBlock['tool_use_id']);
        self::assertSame('ping', $resultBlock['content']);
    }

    public function testEmitsPsr3LogRecords(): void
    {
        $client = new class () implements ClientInterface {
            public int $calls = 0;

            public function sendMessages(array $messages, array $tools): array
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return [
                        'stop_reason' => 'tool_use',
                        'content' => [
                            [
                                'type' => 'tool_use',
                                'id' => 'toolu_log',
                                'name' => 'echo',
                                'input' => ['text' => 'hi'],
                            ],
                        ],
                    ];
                }

                return [
                    'stop_reason' => 'end_turn',
                    'content' => [['type' => 'text', 'text' => 'done']],
                ];
            }
        };

        $registry = new ToolRegistry();
        $registry->register(new class () implements Tool {
            public function name(): string
            {
                return 'echo';
            }

            public function description(): string
            {
                return 'echoes';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function execute(array $input): string
            {
                $text = $input['text'] ?? '';

                return is_string($text) ? $text : '';
            }
        });

        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $loop = new AgentLoop($client, $registry, $logger);
        $loop->run('say hi');

        $levels = array_map(static fn (array $r): mixed => $r['level'], $logger->records);
        self::assertContains(LogLevel::INFO, $levels, 'Expected at least one info record (turn boundary).');
        self::assertContains(LogLevel::DEBUG, $levels, 'Expected at least one debug record (tool call).');

        $toolDebug = array_filter(
            $logger->records,
            static fn (array $r): bool => $r['level'] === LogLevel::DEBUG
                && ($r['context']['tool'] ?? null) === 'echo',
        );
        self::assertNotEmpty($toolDebug, 'Expected a debug record with tool=echo in context.');
    }

    public function testThrowsWhenIterationCapExceeded(): void
    {
        $client = new class () implements ClientInterface {
            public function sendMessages(array $messages, array $tools): array
            {
                return [
                    'stop_reason' => 'tool_use',
                    'content' => [
                        [
                            'type' => 'tool_use',
                            'id' => 'toolu_loop',
                            'name' => 'noop',
                            'input' => [],
                        ],
                    ],
                ];
            }
        };

        $registry = new ToolRegistry();
        $registry->register(new class () implements Tool {
            public function name(): string
            {
                return 'noop';
            }

            public function description(): string
            {
                return 'No-op.';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function execute(array $input): string
            {
                return 'ok';
            }
        });

        $loop = new AgentLoop($client, $registry);

        $this->expectException(LoopLimitException::class);
        $loop->run('go');
    }
}
