<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface ProjectInvoiceManager
{
    /** @return array<string, mixed> */
    public function list(int $projectId): array;

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function record(array $arguments): array;
}
