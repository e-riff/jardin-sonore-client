<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface McpSessionWriteCoordinator
{
    /** @param list<string> $sessionUuids
     * @param callable(): array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    public function execute(array $sessionUuids, ?string $familyUuid, callable $operation): array;

    /** @param callable(): array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    public function executeCreation(string $tool, string $requestId, callable $operation): array;
}
