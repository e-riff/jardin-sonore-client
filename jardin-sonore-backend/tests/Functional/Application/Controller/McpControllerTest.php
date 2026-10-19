<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Kernel;
use stdClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class McpControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private false|string $previousBearerToken;

    private bool $previousEnvBearerTokenDefined;

    private ?string $previousEnvBearerToken;

    private bool $previousServerBearerTokenDefined;

    private ?string $previousServerBearerToken;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true);
    }

    protected function setUp(): void
    {
        $this->previousBearerToken = getenv('MCP_BEARER_TOKEN');
        $this->previousEnvBearerTokenDefined = array_key_exists('MCP_BEARER_TOKEN', $_ENV);
        $this->previousEnvBearerToken = $_ENV['MCP_BEARER_TOKEN'] ?? null;
        $this->previousServerBearerTokenDefined = array_key_exists('MCP_BEARER_TOKEN', $_SERVER);
        $this->previousServerBearerToken = $_SERVER['MCP_BEARER_TOKEN'] ?? null;
        $_ENV['MCP_BEARER_TOKEN'] = 'test-mcp-token';
        $_SERVER['MCP_BEARER_TOKEN'] = 'test-mcp-token';
        putenv('MCP_BEARER_TOKEN=test-mcp-token');
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousEnvBearerTokenDefined) {
            $_ENV['MCP_BEARER_TOKEN'] = $this->previousEnvBearerToken;
        } else {
            unset($_ENV['MCP_BEARER_TOKEN']);
        }

        if ($this->previousServerBearerTokenDefined) {
            $_SERVER['MCP_BEARER_TOKEN'] = $this->previousServerBearerToken;
        } else {
            unset($_SERVER['MCP_BEARER_TOKEN']);
        }

        if (false === $this->previousBearerToken) {
            putenv('MCP_BEARER_TOKEN');
        } else {
            putenv("MCP_BEARER_TOKEN={$this->previousBearerToken}");
        }
    }

    public function testInitializeNegotiatesMcpCapabilities(): void
    {
        $this->client->jsonRequest('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new stdClass(),
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0'],
            ],
        ], ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream']);

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('2.0', $response['jsonrpc'] ?? null);
        self::assertSame(1, $response['id'] ?? null);
        self::assertSame('2025-06-18', $response['result']['protocolVersion'] ?? null);
        self::assertArrayHasKey('tools', $response['result']['capabilities'] ?? []);
        self::assertSame('jardin-sonore', $response['result']['serverInfo']['name'] ?? null);
    }

    public function testInitializeSupportsMistralProtocolVersion(): void
    {
        $this->client->jsonRequest('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new stdClass(),
                'clientInfo' => ['name' => 'mistral-mcp-client', 'version' => '1.0'],
            ],
        ], ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream']);

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('2025-03-26', $response['result']['protocolVersion'] ?? null);
        self::assertArrayHasKey('tools', $response['result']['capabilities'] ?? []);
    }

    public function testInitializeCountersUnsupportedVersionWithLatestSupportedVersion(): void
    {
        $this->client->jsonRequest('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2099-01-01',
                'capabilities' => new stdClass(),
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0'],
            ],
        ], ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream']);

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('2025-06-18', $response['result']['protocolVersion'] ?? null);
    }

    public function testInitializedNotificationReturnsAcceptedWithoutBody(): void
    {
        $this->client->jsonRequest('POST', '/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream']);

        self::assertResponseStatusCodeSame(202);
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    public function testUnimplementedCapabilityListsReturnEmptyLists(): void
    {
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];

        foreach (['prompts/list' => 'prompts', 'resources/list' => 'resources'] as $method => $key) {
            $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => new stdClass()], $headers);

            self::assertResponseIsSuccessful();
            $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame([], $response['result'][$key] ?? null);
        }
    }

    public function testMissingOrIncorrectBearerTokenIsRejected(): void
    {
        foreach ([[], ['HTTP_AUTHORIZATION' => 'Bearer wrong-token']] as $server) {
            $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'], $server);

            self::assertResponseStatusCodeSame(401);
            self::assertStringNotContainsString('test-mcp-token', (string) $this->client->getResponse()->getContent());
        }
    }

    public function testGetAndCrossOriginRequestsAreRejected(): void
    {
        $this->client->request('GET', '/api/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'text/event-stream']);
        self::assertResponseStatusCodeSame(405);

        $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'], [
            'HTTP_AUTHORIZATION' => 'Bearer test-mcp-token',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_ORIGIN' => 'https://attacker.example',
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidJsonAndUnknownMethodReturnJsonRpcErrors(): void
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        $this->client->request('POST', '/api/mcp', server: [...$server, 'CONTENT_TYPE' => 'application/json'], content: '{');
        self::assertResponseStatusCodeSame(400);
        self::assertSame(-32700, json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['error']['code']);

        $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'unknown/method'], $server);
        self::assertResponseIsSuccessful();
        self::assertSame(-32601, json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['error']['code']);
    }

    public function testListsCommercialReadAndWriteToolsWhileWritesRemainDisabled(): void
    {
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $headers);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names = array_column($response['result']['tools'], 'name');
        self::assertCount(16, $names);
        self::assertContains('find_sent_replies', $names);
        self::assertContains('save_mail_draft', $names);
        self::assertContains('read_mail_draft', $names);
        self::assertContains('update_mail_draft', $names);
        self::assertContains('create_mail_draft', $names);
        self::assertContains('get_project_context', $names);
        self::assertContains('record_mail_summary', $names);
        self::assertContains('create_project_reminder', $names);

        $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => [
            'name' => 'record_mail_summary',
            'arguments' => ['projectId' => 1, 'mailReference' => 'irrelevant', 'summary' => 'Résumé'],
        ]], $headers);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($response['result']['isError']);

        $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'save_mail_draft',
            'arguments' => ['mailReference' => 'irrelevant', 'body' => 'Bonjour'],
        ]], $headers);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($response['result']['isError']);
        self::assertSame('Draft writes are disabled.', $response['result']['content'][0]['text']);
    }

    public function testEveryListedToolHasAnObjectForSchemaProperties(): void
    {
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer test-mcp-token', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        $this->client->jsonRequest('POST', '/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $headers);

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), false, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $response);

        foreach ($response->result->tools as $tool) {
            self::assertInstanceOf(stdClass::class, $tool->inputSchema->properties, $tool->name);
        }
    }
}
