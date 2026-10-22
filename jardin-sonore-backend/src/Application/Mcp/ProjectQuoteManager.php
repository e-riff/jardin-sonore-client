<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface ProjectQuoteManager
{
    /** @return list<array<string, mixed>> */
    public function list(int $projectId): array;

    /** @return array<string, mixed> */
    public function get(int $projectId, int $quoteId): array;

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(string $operation, array $arguments): array;
}
