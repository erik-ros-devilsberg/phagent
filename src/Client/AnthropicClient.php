<?php

declare(strict_types=1);

namespace Phagent\Client;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;

final class AnthropicClient implements ClientInterface
{
    public const string MODEL = 'claude-sonnet-4-6';
    public const int MAX_TOKENS = 1024;
    public const string API_URL = 'https://api.anthropic.com/v1/messages';
    public const string API_VERSION = '2023-06-01';

    public function __construct(
        private readonly GuzzleClientInterface $http,
        private readonly string $apiKey,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Anthropic API key must not be empty.');
        }
    }

    public static function fromEnvironment(?GuzzleClientInterface $http = null): self
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (!is_string($apiKey) || $apiKey === '') {
            throw new \RuntimeException(
                'ANTHROPIC_API_KEY environment variable is not set.',
            );
        }

        return new self($http ?? new \GuzzleHttp\Client(), $apiKey);
    }

    public function sendMessages(array $messages, array $tools): array
    {
        $payload = [
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => $messages,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = $this->http->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $body = (string) $response->getBody();
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

        return $result;
    }
}
