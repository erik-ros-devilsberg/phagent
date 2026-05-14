<?php

declare(strict_types=1);

namespace Phagent\Tests\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Phagent\Client\AnthropicClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(AnthropicClient::class)]
final class AnthropicClientTest extends TestCase
{
    /** @var list<RequestInterface> */
    private array $captured = [];

    protected function setUp(): void
    {
        $this->captured = [];
    }

    public function testUsesDefaultModelAndMaxTokensWhenUnconfigured(): void
    {
        $client = $this->makeClient();

        $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        $body = $this->lastRequestBody();
        self::assertSame(AnthropicClient::DEFAULT_MODEL, $body['model'] ?? null);
        self::assertSame(AnthropicClient::DEFAULT_MAX_TOKENS, $body['max_tokens'] ?? null);
    }

    public function testForwardsConfiguredModelAndMaxTokens(): void
    {
        $client = $this->makeClient(
            model: 'claude-haiku-4-5-20251001',
            maxTokens: 256,
        );

        $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        $body = $this->lastRequestBody();
        self::assertSame('claude-haiku-4-5-20251001', $body['model'] ?? null);
        self::assertSame(256, $body['max_tokens'] ?? null);
    }

    public function testIncludesSystemPromptWhenProvided(): void
    {
        $client = $this->makeClient();

        $client->sendMessages(
            [['role' => 'user', 'content' => 'hi']],
            [],
            'You are a terse assistant.',
        );

        $body = $this->lastRequestBody();
        self::assertSame('You are a terse assistant.', $body['system'] ?? null);
    }

    public function testOmitsSystemKeyWhenNull(): void
    {
        $client = $this->makeClient();

        $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        $body = $this->lastRequestBody();
        self::assertArrayNotHasKey('system', $body);
    }

    public function testRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AnthropicClient(new GuzzleClient(), '');
    }

    private function makeClient(
        string $model = AnthropicClient::DEFAULT_MODEL,
        int $maxTokens = AnthropicClient::DEFAULT_MAX_TOKENS,
    ): AnthropicClient {
        $handler = function (RequestInterface $request): PromiseInterface {
            $this->captured[] = $request;

            return Create::promiseFor(new Response(
                200,
                [],
                (string) json_encode([
                    'stop_reason' => 'end_turn',
                    'content' => [['type' => 'text', 'text' => 'ok']],
                ]),
            ));
        };

        $http = new GuzzleClient(['handler' => $handler]);

        return new AnthropicClient($http, 'test-key', $model, $maxTokens);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function lastRequestBody(): array
    {
        self::assertNotEmpty($this->captured, 'No HTTP request was captured.');
        $request = $this->captured[count($this->captured) - 1];

        $decoded = json_decode((string) $request->getBody(), true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
