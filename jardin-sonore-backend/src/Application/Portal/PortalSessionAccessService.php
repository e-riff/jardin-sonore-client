<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;

final readonly class PortalSessionAccessService
{
    public function __construct(private PortalSessionReader $portalSessionReader)
    {
    }

    public function findAuthorizedSession(UserEntity $userEntity, string $sessionSlug): ?SessionSummaryEntity
    {
        return $this->portalSessionReader->findAuthorizedBySlug($userEntity, $sessionSlug);
    }
}
