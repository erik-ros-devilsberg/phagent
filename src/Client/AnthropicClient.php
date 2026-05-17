<?php

declare(strict_types=1);

namespace Phagent\Client;

use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class AnthropicClient implements ClientInterface
{
    public const string DEFAULT_MODEL = 'claude-sonnet-4-6';
    public const int DEFAULT_MAX_TOKENS = 1024;
    public const string API_URL = 'https://api.anthropic.com/v1/messages';
    public const string API_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly int $maxTokens = self::DEFAULT_MAX_TOKENS,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Anthropic API key must not be empty.');
        }
    }

    public static function fromEnvironment(
        ?HttpClientInterface $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new \RuntimeException(
                'ANTHROPIC_API_KEY environment variable is not set.',
            );
        }

        if ($http === null || $requestFactory === null || $streamFactory === null) {
            // Guarded fallback: only loaded when caller does not supply a PSR-18/17 stack.
            if (!class_exists(\GuzzleHttp\Client::class) || !class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
                throw new \RuntimeException(
                    'No PSR-18 HTTP client or PSR-17 factories were provided, and the default '
                    . 'Guzzle fallback (guzzlehttp/guzzle) is not installed. Either install Guzzle '
                    . 'or pass your own client and factories to AnthropicClient::fromEnvironment().',
                );
            }
            $factory = new \GuzzleHttp\Psr7\HttpFactory();
            $http ??= new \GuzzleHttp\Client();
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }

        return new self($http, $requestFactory, $streamFactory, $apiKey);
    }

    public function sendMessages(array $messages, array $tools, ?string $systemPrompt = null): array
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $this->normaliseMessages($messages),
        ];
        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $request = $this->requestFactory->createRequest('POST', self::API_URL)
            ->withHeader('x-api-key', $this->apiKey)
            ->withHeader('anthropic-version', self::API_VERSION)
            ->withHeader('content-type', 'application/json')
            ->withBody($this->streamFactory->createStream(
                json_encode($payload, JSON_THROW_ON_ERROR),
            ));

        $response = $this->http->sendRequest($request);

        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf(
                'Anthropic API returned HTTP %d: %s',
                $status,
                $body,
            ));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Anthropic API returned a non-JSON response.');
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('Anthropic API response has non-string keys.');
            }
            $result[$key] = $value;
        }

        $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
        $result['usage'] = [
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
        ];

        return $result;
    }

    /**
     * Anthropic's schema requires `tool_use.input` to be a JSON object. PHP's
     * `json_decode(..., true)` collapses `{}` and `[]` to the same `[]`, so an
     * empty input round-trips back as a JSON array and the API rejects it.
     * Cast empty arrays to `(object) []` here so `json_encode` emits `{}`.
     *
     * @param list<array<string, mixed>> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function normaliseMessages(array $messages): array
    {
        foreach ($messages as $i => $message) {
            $content = $message['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }
            foreach ($content as $j => $block) {
                if (!is_array($block)) {
                    continue;
                }
                if (($block['type'] ?? null) === 'tool_use' && ($block['input'] ?? null) === []) {
                    $block['input'] = (object) [];
                    $content[$j] = $block;
                }
            }
            $message['content'] = $content;
            $messages[$i] = $message;
        }

        return $messages;
    }
}
