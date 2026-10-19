<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final class McpProtocol
{
    public const string PROTOCOL_VERSION = '2025-06-18';

    private const array SUPPORTED_PROTOCOL_VERSIONS = ['2025-03-26', self::PROTOCOL_VERSION];

    public function __construct(private readonly ReadToolRegistry $toolRegistry, private readonly WriteToolRegistry $writeToolRegistry)
    {
    }

    /** @param array<string, mixed> $message
     * @return array<string, mixed>|null
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $hasId = array_key_exists('id', $message);
        if ('2.0' !== ($message['jsonrpc'] ?? null) || !is_string($message['method'] ?? null)) {
            return $this->error($hasId ? $id : null, -32600, 'Invalid Request');
        }

        $method = $message['method'];
        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return $this->error($hasId ? $id : null, -32602, 'Invalid params');
        }

        if (!$hasId) {
            return str_starts_with($method, 'notifications/') ? null : null;
        }

        if ('initialize' === $method) {
            $requestedVersion = $params['protocolVersion'] ?? null;
            if (!is_string($requestedVersion)) {
                return $this->error($id, -32602, 'Invalid params');
            }

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => in_array($requestedVersion, self::SUPPORTED_PROTOCOL_VERSIONS, true)
                        ? $requestedVersion
                        : self::PROTOCOL_VERSION,
                    'capabilities' => ['tools' => (object) []],
                    'serverInfo' => ['name' => 'jardin-sonore', 'version' => '1.0.0'],
                ],
            ];
        }

        if ('ping' === $method) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) []];
        }

        if ('tools/list' === $method) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => [...$this->toolRegistry->definitions(), ...$this->writeToolRegistry->definitions()]]];
        }

        if ('prompts/list' === $method) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['prompts' => []]];
        }

        if ('resources/list' === $method) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['resources' => []]];
        }

        if ('tools/call' === $method) {
            $name = $params['name'] ?? null;
            $arguments = $params['arguments'] ?? [];
            if (!is_string($name) || !is_array($arguments) || ([] !== $arguments && array_is_list($arguments))) {
                return $this->error($id, -32602, 'Invalid params');
            }

            $writeNames = array_column($this->writeToolRegistry->definitions(), 'name');

            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => in_array($name, $writeNames, true)
                ? $this->writeToolRegistry->call($name, $arguments)
                : $this->toolRegistry->call($name, $arguments)];
        }

        return $this->error($id, -32601, 'Method not found');
    }

    /** @return array<string, mixed> */
    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ];
    }
}
