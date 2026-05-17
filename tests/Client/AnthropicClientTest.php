<?php

declare(strict_types=1);

namespace Phagent\Tests\Client;

use GuzzleHttp\Psr7\HttpFactory;
use Phagent\Client\AnthropicClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(AnthropicClient::class)]
final class AnthropicClientTest extends TestCase
{
    /** @var list<RequestInterface> */
    private array $captured = [];

    private string $nextResponseBody = '';

    protected function setUp(): void
    {
        $this->captured = [];
        $this->nextResponseBody = (string) json_encode([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'ok']],
        ]);
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

    public function testSendsPsr7RequestWithExpectedShape(): void
    {
        $client = $this->makeClient();

        $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        self::assertNotEmpty($this->captured);
        $request = $this->captured[count($this->captured) - 1];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(AnthropicClient::API_URL, (string) $request->getUri());
        self::assertSame('test-key', $request->getHeaderLine('x-api-key'));
        self::assertSame(AnthropicClient::API_VERSION, $request->getHeaderLine('anthropic-version'));
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
    }

    public function testRejectsEmptyApiKey(): void
    {
        $factory = new HttpFactory();
        $this->expectException(\InvalidArgumentException::class);
        new AnthropicClient($this->makeHttpFake(), $factory, $factory, '');
    }

    public function testEmptyToolUseInputSerializedAsObject(): void
    {
        $client = $this->makeClient();

        $client->sendMessages([
            ['role' => 'user', 'content' => 'what time is it'],
            ['role' => 'assistant', 'content' => [
                ['type' => 'tool_use', 'id' => 'call_abc', 'name' => 'get_current_time', 'input' => []],
            ]],
            ['role' => 'user', 'content' => [
                ['type' => 'tool_result', 'tool_use_id' => 'call_abc', 'content' => '1700000000'],
            ]],
        ], []);

        self::assertNotEmpty($this->captured);
        $rawBody = (string) $this->captured[count($this->captured) - 1]->getBody();
        self::assertStringContainsString('"input":{}', $rawBody);
        self::assertStringNotContainsString('"input":[]', $rawBody);
    }

    public function testNonEmptyToolUseInputIsUnaffected(): void
    {
        $client = $this->makeClient();

        $client->sendMessages([
            ['role' => 'user', 'content' => 'weather'],
            ['role' => 'assistant', 'content' => [
                ['type' => 'tool_use', 'id' => 'call_xyz', 'name' => 'get_weather', 'input' => ['city' => 'Berlin']],
            ]],
            ['role' => 'user', 'content' => [
                ['type' => 'tool_result', 'tool_use_id' => 'call_xyz', 'content' => 'sunny'],
            ]],
        ], []);

        $body = $this->lastRequestBody();
        $messages = $body['messages'] ?? null;
        self::assertIsArray($messages);
        $assistant = $messages[1] ?? null;
        self::assertIsArray($assistant);
        $content = $assistant['content'] ?? null;
        self::assertIsArray($content);
        $block = $content[0] ?? null;
        self::assertIsArray($block);
        self::assertSame(['city' => 'Berlin'], $block['input'] ?? null);
    }

    public function testFromEnvironmentBuildsClientWithoutArguments(): void
    {
        $previous = getenv('ANTHROPIC_API_KEY');
        putenv('ANTHROPIC_API_KEY=test-key');

        try {
            $client = AnthropicClient::fromEnvironment();
            self::assertInstanceOf(AnthropicClient::class, $client);
        } finally {
            if ($previous === false) {
                putenv('ANTHROPIC_API_KEY');
            } else {
                putenv('ANTHROPIC_API_KEY=' . $previous);
            }
        }
    }

    public function testUsageBlockIsReturnedInNeutralShape(): void
    {
        $this->nextResponseBody = (string) json_encode([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => [
                'input_tokens' => 123,
                'output_tokens' => 45,
            ],
        ]);
        $client = $this->makeClient();

        $result = $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        self::assertSame(
            ['input_tokens' => 123, 'output_tokens' => 45],
            $result['usage'] ?? null,
        );
    }

    public function testMissingUsageDefaultsToZeros(): void
    {
        $this->nextResponseBody = (string) json_encode([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'ok']],
        ]);
        $client = $this->makeClient();

        $result = $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        self::assertSame(
            ['input_tokens' => 0, 'output_tokens' => 0],
            $result['usage'] ?? null,
        );
    }

    private function makeClient(
        string $model = AnthropicClient::DEFAULT_MODEL,
        int $maxTokens = AnthropicClient::DEFAULT_MAX_TOKENS,
    ): AnthropicClient {
        $factory = new HttpFactory();

        return new AnthropicClient(
            $this->makeHttpFake(),
            $factory,
            $factory,
            'test-key',
            $model,
            $maxTokens,
        );
    }

    private function makeHttpFake(): HttpClientInterface
    {
        $factory = new HttpFactory();
        $capture = function (RequestInterface $request): void {
            $this->captured[] = $request;
        };
        $responseBody = function (): string {
            return $this->nextResponseBody;
        };

        return new class ($factory, $capture, $responseBody) implements HttpClientInterface {
            /**
             * @param \Closure(RequestInterface): void $capture
             * @param \Closure(): string               $responseBody
             */
            public function __construct(
                private readonly HttpFactory $factory,
                private readonly \Closure $capture,
                private readonly \Closure $responseBody,
            ) {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ($this->capture)($request);

                return $this->factory->createResponse(200)
                    ->withBody($this->factory->createStream(($this->responseBody)()));
            }
        };
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
