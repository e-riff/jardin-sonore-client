<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSummary;
use Symfony\Component\Uid\Uuid;

interface SessionDocumentGenerationStore
{
    public function claimDocument(Uuid $uuid, string $token): ?SessionSummary;

    public function finishDocument(Uuid $uuid, string $token, ?string $stagedPath, ?string $error): bool;
}
