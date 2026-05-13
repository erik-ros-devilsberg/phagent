<?php

declare(strict_types=1);

namespace Phagent;

use Phagent\Client\ClientInterface;
use Phagent\Exception\LoopLimitException;
use Phagent\Tool\ToolRegistry;

final class AgentLoop
{
    public const int MAX_TURNS = 10;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly ToolRegistry $tools,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function run(string $prompt): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];
        $this->log(0, 'user', $prompt);

        for ($turn = 1; $turn <= self::MAX_TURNS; $turn++) {
            $response = $this->client->sendMessages($messages, $this->tools->allSchemas());

            $stopReason = $this->stringField($response, 'stop_reason');
            $content = $this->listField($response, 'content');

            $messages[] = ['role' => 'assistant', 'content' => $content];
            $this->log($turn, 'assistant', $this->summarize($content));

            if ($stopReason !== 'tool_use') {
                return $this->finalText($content);
            }

            $toolResults = $this->runToolCalls($content, $turn);
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        throw new LoopLimitException(
            sprintf('Agent loop exceeded %d turns without completing.', self::MAX_TURNS),
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
            $this->log($turn, 'tool_result', sprintf('%s → %s', $name, $output));

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

    private function log(int $turn, string $role, string $text): void
    {
        $this->logger?->log($turn, $role, $text);
    }
}
