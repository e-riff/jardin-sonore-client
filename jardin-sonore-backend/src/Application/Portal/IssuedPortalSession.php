<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\PortalSessionEntity;

final readonly class IssuedPortalSession
{
    public function __construct(public PortalSessionEntity $portalSessionEntity, public string $rawToken)
    {
    }
}
