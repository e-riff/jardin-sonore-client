<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final class McpProtocol
{
    public const string PROTOCOL_VERSION = '2025-06-18';

    public function __construct(private readonly ReadToolRegistry $toolRegistry)
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

            if (self::PROTOCOL_VERSION !== $requestedVersion) {
                return $this->error($id, -32602, 'Unsupported protocol version');
            }

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => self::PROTOCOL_VERSION,
                    'capabilities' => ['tools' => (object) []],
                    'serverInfo' => ['name' => 'jardin-sonore', 'version' => '1.0.0'],
                ],
            ];
        }

        if ('ping' === $method) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) []];
        }

        if ('tools/list' === $method) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => $this->toolRegistry->definitions()]];
        }

        if ('tools/call' === $method) {
            $name = $params['name'] ?? null;
            $arguments = $params['arguments'] ?? [];
            if (!is_string($name) || !is_array($arguments) || ([] !== $arguments && array_is_list($arguments))) {
                return $this->error($id, -32602, 'Invalid params');
            }

            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $this->toolRegistry->call($name, $arguments)];
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
