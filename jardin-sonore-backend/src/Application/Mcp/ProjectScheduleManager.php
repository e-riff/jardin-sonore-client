<?php

declare(strict_types=1);

namespace App\Application\Mcp;

interface ProjectScheduleManager
{
    /** @return array<string, mixed> */
    public function getAppointment(int $projectId, int $appointmentId): array;

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(string $operation, array $arguments): array;
}
