<?php

declare(strict_types=1);

namespace Phagent\Tests\Client;

use GuzzleHttp\Psr7\HttpFactory;
use Phagent\Client\OpenAIClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(OpenAIClient::class)]
final class OpenAIClientTest extends TestCase
{
    /** @var list<RequestInterface> */
    private array $captured = [];

    /** @var string */
    private string $nextResponseBody = '';

    protected function setUp(): void
    {
        $this->captured = [];
        $this->nextResponseBody = (string) json_encode([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'finish_reason' => 'stop',
            ]],
        ]);
    }

    public function testTextOnlyHappyPathReturnsNeutralEndTurnShape(): void
    {
        $client = $this->makeClient();

        $result = $client->sendMessages(
            [['role' => 'user', 'content' => 'hi']],
            [],
        );

        self::assertSame('end_turn', $result['stop_reason'] ?? null);
        self::assertSame(
            [['type' => 'text', 'text' => 'ok']],
            $result['content'] ?? null,
        );
    }

    public function testToolCallResponseTranslatesToNeutralToolUseShape(): void
    {
        $this->nextResponseBody = (string) json_encode([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_abc',
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_current_time',
                            'arguments' => '{"tz":"UTC"}',
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
        ]);
        $client = $this->makeClient();

        $result = $client->sendMessages(
            [['role' => 'user', 'content' => 'what time is it']],
            [['name' => 'get_current_time', 'description' => 'Now.', 'input_schema' => ['type' => 'object', 'properties' => (object) []]]],
        );

        self::assertSame('tool_use', $result['stop_reason'] ?? null);
        self::assertSame(
            [[
                'type' => 'tool_use',
                'id' => 'call_abc',
                'name' => 'get_current_time',
                'input' => ['tz' => 'UTC'],
            ]],
            $result['content'] ?? null,
        );
    }

    public function testEmptyToolCallArgumentsBecomeEmptyArray(): void
    {
        $this->nextResponseBody = (string) json_encode([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_xyz',
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_current_time',
                            'arguments' => '{}',
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
        ]);
        $client = $this->makeClient();

        $result = $client->sendMessages([['role' => 'user', 'content' => 'time?']], []);

        $content = $result['content'] ?? null;
        self::assertIsArray($content);
        self::assertSame([], $content[0]['input'] ?? null);
    }

    public function testToolResultRoundTripsAsRoleToolMessage(): void
    {
        $client = $this->makeClient();

        $client->sendMessages(
            [
                ['role' => 'user', 'content' => 'what time is it'],
                ['role' => 'assistant', 'content' => [
                    ['type' => 'tool_use', 'id' => 'call_abc', 'name' => 'get_current_time', 'input' => []],
                ]],
                ['role' => 'user', 'content' => [
                    ['type' => 'tool_result', 'tool_use_id' => 'call_abc', 'content' => '1700000000'],
                ]],
            ],
            [],
        );

        $body = $this->lastRequestBody();
        $messages = $body['messages'] ?? null;
        self::assertIsArray($messages);

        $assistant = $messages[1] ?? null;
        self::assertIsArray($assistant);
        self::assertSame('assistant', $assistant['role'] ?? null);
        self::assertSame([[
            'id' => 'call_abc',
            'type' => 'function',
            'function' => [
                'name' => 'get_current_time',
                'arguments' => '{}',
            ],
        ]], $assistant['tool_calls'] ?? null);

        $tool = $messages[2] ?? null;
        self::assertIsArray($tool);
        self::assertSame('tool', $tool['role'] ?? null);
        self::assertSame('call_abc', $tool['tool_call_id'] ?? null);
        self::assertSame('1700000000', $tool['content'] ?? null);
    }

    public function testFromEnvironmentThrowsWhenApiKeyMissing(): void
    {
        $previous = getenv('OPENAI_API_KEY');
        putenv('OPENAI_API_KEY');

        try {
            $this->expectException(\RuntimeException::class);
            OpenAIClient::fromEnvironment();
        } finally {
            if ($previous !== false) {
                putenv('OPENAI_API_KEY=' . $previous);
            }
        }
    }

    public function testFromEnvironmentBuildsClientWithoutArguments(): void
    {
        $previous = getenv('OPENAI_API_KEY');
        putenv('OPENAI_API_KEY=test-key');

        try {
            $client = OpenAIClient::fromEnvironment();
            self::assertInstanceOf(OpenAIClient::class, $client);
        } finally {
            if ($previous === false) {
                putenv('OPENAI_API_KEY');
            } else {
                putenv('OPENAI_API_KEY=' . $previous);
            }
        }
    }

    public function testNonDefaultBaseUrlIsHonouredOnOutgoingRequest(): void
    {
        $client = $this->makeClient(baseUrl: 'https://api.groq.com/openai/v1/chat/completions');

        $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        self::assertNotEmpty($this->captured);
        $request = $this->captured[count($this->captured) - 1];
        self::assertSame(
            'https://api.groq.com/openai/v1/chat/completions',
            (string) $request->getUri(),
        );
    }

    public function testFromEnvironmentHonoursOpenAiBaseUrlEnv(): void
    {
        $previousKey = getenv('OPENAI_API_KEY');
        $previousUrl = getenv('OPENAI_BASE_URL');
        putenv('OPENAI_API_KEY=test-key');
        putenv('OPENAI_BASE_URL=https://api.together.xyz/v1/chat/completions');

        try {
            $factory = new HttpFactory();
            $http = $this->makeHttpFake();
            $client = OpenAIClient::fromEnvironment($http, $factory, $factory);
            $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

            self::assertNotEmpty($this->captured);
            $request = $this->captured[count($this->captured) - 1];
            self::assertSame(
                'https://api.together.xyz/v1/chat/completions',
                (string) $request->getUri(),
            );
        } finally {
            if ($previousKey === false) {
                putenv('OPENAI_API_KEY');
            } else {
                putenv('OPENAI_API_KEY=' . $previousKey);
            }
            if ($previousUrl === false) {
                putenv('OPENAI_BASE_URL');
            } else {
                putenv('OPENAI_BASE_URL=' . $previousUrl);
            }
        }
    }

    public function testRejectsEmptyApiKey(): void
    {
        $factory = new HttpFactory();
        $this->expectException(\InvalidArgumentException::class);
        new OpenAIClient($this->makeHttpFake(), $factory, $factory, '');
    }

    public function testSendsBearerAuthorizationHeader(): void
    {
        $client = $this->makeClient();

        $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        self::assertNotEmpty($this->captured);
        $request = $this->captured[count($this->captured) - 1];
        self::assertSame('Bearer test-key', $request->getHeaderLine('authorization'));
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
    }

    public function testFinishReasonLengthMapsToMaxTokens(): void
    {
        $this->nextResponseBody = (string) json_encode([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'truncated'],
                'finish_reason' => 'length',
            ]],
        ]);
        $client = $this->makeClient();

        $result = $client->sendMessages([['role' => 'user', 'content' => 'hi']], []);

        self::assertSame('max_tokens', $result['stop_reason'] ?? null);
    }

    public function testTranslatesNeutralToolSchemaToOpenAiFormat(): void
    {
        $client = $this->makeClient();

        $client->sendMessages(
            [['role' => 'user', 'content' => 'hi']],
            [[
                'name' => 'get_current_time',
                'description' => 'Returns the current Unix timestamp.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ]],
        );

        $body = $this->lastRequestBody();
        self::assertSame([[
            'type' => 'function',
            'function' => [
                'name' => 'get_current_time',
                'description' => 'Returns the current Unix timestamp.',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]], $body['tools'] ?? null);
    }

    private function makeClient(
        string $model = OpenAIClient::DEFAULT_MODEL,
        string $baseUrl = OpenAIClient::DEFAULT_BASE_URL,
    ): OpenAIClient {
        $factory = new HttpFactory();

        return new OpenAIClient(
            $this->makeHttpFake(),
            $factory,
            $factory,
            'test-key',
            $model,
            $baseUrl,
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
