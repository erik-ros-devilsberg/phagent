<?php

declare(strict_types=1);

namespace Phagent;

use Phagent\Client\ClientInterface;
use Phagent\Exception\LoopLimitException;
use Phagent\Tool\ToolRegistry;
use Psr\Log\LoggerInterface;

final class AgentLoop
{
    public const int DEFAULT_MAX_TURNS = 10;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly ToolRegistry $tools,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $maxTurns = self::DEFAULT_MAX_TURNS,
    ) {
    }

    public function run(string $prompt, ?string $systemPrompt = null): AgentResult
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];
        $this->logger?->info('user prompt', ['prompt' => $prompt]);

        for ($turn = 1; $turn <= $this->maxTurns; $turn++) {
            $response = $this->client->sendMessages($messages, $this->tools->allSchemas(), $systemPrompt);

            $stopReason = $this->stringField($response, 'stop_reason');
            $content = $this->listField($response, 'content');

            $messages[] = ['role' => 'assistant', 'content' => $content];
            $this->logger?->info('assistant turn', [
                'turn' => $turn,
                'summary' => $this->summarize($content),
                'stop_reason' => $stopReason,
            ]);

            if ($stopReason !== 'tool_use') {
                return new AgentResult(
                    text: $this->finalText($content),
                    stopReason: $stopReason,
                    turns: $turn,
                );
            }

            $toolResults = $this->runToolCalls($content, $turn);
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        throw new LoopLimitException(
            sprintf('Agent loop exceeded %d turns without completing.', $this->maxTurns),
        );
    }

    /**
     * @param list<array<string, mixed>> $content
     *
     * @return list<array<string, mixed>>
     */
    private function runToolCalls(array $content, int $turn): array
    {
        $results = [];
        foreach ($content as $block) {
            if (($block['type'] ?? null) !== 'tool_use') {
                continue;
            }

            $name = $block['name'] ?? null;
            $id = $block['id'] ?? null;
            $input = $block['input'] ?? [];

            if (!is_string($name) || !is_string($id) || !is_array($input)) {
                throw new \RuntimeException('Malformed tool_use block from the model.');
            }

            /** @var array<string, mixed> $input */
            $output = $this->tools->get($name)->execute($input);
            $this->logger?->debug('tool call', [
                'turn' => $turn,
                'tool' => $name,
                'input' => $input,
                'output' => $output,
            ]);

            $results[] = [
                'type' => 'tool_result',
                'tool_use_id' => $id,
                'content' => $output,
            ];
        }

        return $results;
    }

    /**
     * @param list<array<string, mixed>> $content
     */
    private function finalText(array $content): string
    {
        foreach ($content as $block) {
            if (($block['type'] ?? null) !== 'text') {
                continue;
            }
            $text = $block['text'] ?? null;
            if (is_string($text)) {
                return $text;
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $content
     */
    private function summarize(array $content): string
    {
        $parts = [];
        foreach ($content as $block) {
            $type = $block['type'] ?? null;
            if ($type === 'text' && is_string($block['text'] ?? null)) {
                /** @var string $text */
                $text = $block['text'];
                $parts[] = $text;
            } elseif ($type === 'tool_use' && is_string($block['name'] ?? null)) {
                /** @var string $name */
                $name = $block['name'];
                $parts[] = sprintf('[tool_use: %s]', $name);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function stringField(array $response, string $key): string
    {
        $value = $response[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('Expected string at "%s" in API response.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return list<array<string, mixed>>
     */
    private function listField(array $response, string $key): array
    {
        $value = $response[$key] ?? null;
        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Expected array at "%s" in API response.', $key));
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException(sprintf('Expected array elements in "%s".', $key));
            }
            /** @var array<string, mixed> $item */
            $list[] = $item;
        }

        return $list;
    }
}
