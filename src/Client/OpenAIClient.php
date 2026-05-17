<?php

declare(strict_types=1);

namespace Phagent\Client;

use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class OpenAIClient implements ClientInterface
{
    public const string DEFAULT_MODEL = 'gpt-4o';
    public const int DEFAULT_MAX_TOKENS = 1024;
    public const string DEFAULT_BASE_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $maxTokens = self::DEFAULT_MAX_TOKENS,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('OpenAI API key must not be empty.');
        }
    }

    public static function fromEnvironment(
        ?HttpClientInterface $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new \RuntimeException(
                'OPENAI_API_KEY environment variable is not set.',
            );
        }

        $baseUrl = getenv('OPENAI_BASE_URL');
        if (!is_string($baseUrl) || $baseUrl === '') {
            $baseUrl = self::DEFAULT_BASE_URL;
        }

        if ($http === null || $requestFactory === null || $streamFactory === null) {
            if (!class_exists(\GuzzleHttp\Client::class) || !class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
                throw new \RuntimeException(
                    'No PSR-18 HTTP client or PSR-17 factories were provided, and the default '
                    . 'Guzzle fallback (guzzlehttp/guzzle) is not installed. Either install Guzzle '
                    . 'or pass your own client and factories to OpenAIClient::fromEnvironment().',
                );
            }
            $factory = new \GuzzleHttp\Psr7\HttpFactory();
            $http ??= new \GuzzleHttp\Client();
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }

        return new self(
            $http,
            $requestFactory,
            $streamFactory,
            $apiKey,
            self::DEFAULT_MODEL,
            $baseUrl,
        );
    }

    public function sendMessages(array $messages, array $tools, ?string $systemPrompt = null): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $this->translateMessages($messages, $systemPrompt),
        ];
        if ($tools !== []) {
            $payload['tools'] = $this->translateTools($tools);
        }

        $request = $this->requestFactory->createRequest('POST', $this->baseUrl)
            ->withHeader('authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('content-type', 'application/json')
            ->withBody($this->streamFactory->createStream(
                json_encode($payload, JSON_THROW_ON_ERROR),
            ));

        $response = $this->http->sendRequest($request);

        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf(
                'OpenAI API returned HTTP %d: %s',
                $status,
                $body,
            ));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OpenAI API returned a non-JSON response.');
        }

        return $this->translateResponse($decoded);
    }

    /**
     * @param list<array<string, mixed>> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function translateMessages(array $messages, ?string $systemPrompt): array
    {
        $out = [];
        if ($systemPrompt !== null) {
            $out[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $message) {
            $role = $message['role'] ?? null;
            $content = $message['content'] ?? null;
            if (!is_string($role)) {
                throw new \RuntimeException('Message role must be a string.');
            }

            if (is_string($content)) {
                $out[] = ['role' => $role, 'content' => $content];
                continue;
            }

            if (!is_array($content)) {
                throw new \RuntimeException('Message content must be a string or a list of blocks.');
            }

            $textParts = [];
            $toolCalls = [];
            $toolResults = [];

            foreach ($content as $block) {
                if (!is_array($block)) {
                    throw new \RuntimeException('Content block must be an array.');
                }
                $type = $block['type'] ?? null;
                if ($type === 'text' && is_string($block['text'] ?? null)) {
                    /** @var string $text */
                    $text = $block['text'];
                    $textParts[] = $text;
                } elseif ($type === 'tool_use'
                    && is_string($block['id'] ?? null)
                    && is_string($block['name'] ?? null)
                    && is_array($block['input'] ?? null)
                ) {
                    /** @var string $id */
                    $id = $block['id'];
                    /** @var string $name */
                    $name = $block['name'];
                    /** @var array<string, mixed> $input */
                    $input = $block['input'];
                    $toolCalls[] = [
                        'id' => $id,
                        'type' => 'function',
                        'function' => [
                            'name' => $name,
                            'arguments' => json_encode((object) $input, JSON_THROW_ON_ERROR),
                        ],
                    ];
                } elseif ($type === 'tool_result'
                    && is_string($block['tool_use_id'] ?? null)
                ) {
                    /** @var string $toolUseId */
                    $toolUseId = $block['tool_use_id'];
                    $resultContent = $block['content'] ?? '';
                    $toolResults[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolUseId,
                        'content' => is_string($resultContent)
                            ? $resultContent
                            : json_encode($resultContent, JSON_THROW_ON_ERROR),
                    ];
                }
            }

            if ($role === 'assistant') {
                $message = ['role' => 'assistant'];
                $message['content'] = $textParts === [] ? null : implode('', $textParts);
                if ($toolCalls !== []) {
                    $message['tool_calls'] = $toolCalls;
                }
                $out[] = $message;
            } elseif ($role === 'user' && $toolResults !== []) {
                foreach ($toolResults as $toolMessage) {
                    $out[] = $toolMessage;
                }
                if ($textParts !== []) {
                    $out[] = ['role' => 'user', 'content' => implode('', $textParts)];
                }
            } else {
                $out[] = ['role' => $role, 'content' => implode('', $textParts)];
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $tools
     *
     * @return list<array<string, mixed>>
     */
    private function translateTools(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            $name = $tool['name'] ?? null;
            $description = $tool['description'] ?? '';
            $schema = $tool['input_schema'] ?? ['type' => 'object', 'properties' => (object) []];
            if (!is_string($name)) {
                throw new \RuntimeException('Tool name must be a string.');
            }
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => is_string($description) ? $description : '',
                    'parameters' => is_array($schema) ? $schema : ['type' => 'object', 'properties' => (object) []],
                ],
            ];
        }

        return $out;
    }

    /**
     * @param array<int|string, mixed> $decoded
     *
     * @return array<string, mixed>
     */
    private function translateResponse(array $decoded): array
    {
        $choices = $decoded['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            throw new \RuntimeException('OpenAI response missing choices[0].');
        }
        $choice = $choices[0];

        $finishReason = $choice['finish_reason'] ?? null;
        if (!is_string($finishReason)) {
            throw new \RuntimeException('OpenAI response missing finish_reason.');
        }

        $message = $choice['message'] ?? null;
        if (!is_array($message)) {
            throw new \RuntimeException('OpenAI response missing choices[0].message.');
        }

        $stopReason = match ($finishReason) {
            'stop' => 'end_turn',
            'tool_calls' => 'tool_use',
            'length' => 'max_tokens',
            default => $finishReason,
        };

        $content = [];
        $toolCalls = $message['tool_calls'] ?? null;
        if (is_array($toolCalls)) {
            foreach ($toolCalls as $call) {
                if (!is_array($call)) {
                    continue;
                }
                $id = $call['id'] ?? null;
                $function = $call['function'] ?? null;
                if (!is_string($id) || !is_array($function)) {
                    continue;
                }
                $name = $function['name'] ?? null;
                $argumentsRaw = $function['arguments'] ?? '{}';
                if (!is_string($name) || !is_string($argumentsRaw)) {
                    continue;
                }
                $input = $argumentsRaw === '' ? [] : json_decode($argumentsRaw, true);
                if (!is_array($input)) {
                    $input = [];
                }
                /** @var array<string, mixed> $input */
                $content[] = [
                    'type' => 'tool_use',
                    'id' => $id,
                    'name' => $name,
                    'input' => $input,
                ];
            }
        }

        $text = $message['content'] ?? null;
        if (is_string($text) && $text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        return [
            'stop_reason' => $stopReason,
            'content' => $content,
        ];
    }
}
