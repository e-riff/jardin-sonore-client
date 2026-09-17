<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\PortalImpersonationLaunchEntity;

final readonly class IssuedPortalImpersonationLaunch
{
    public function __construct(public PortalImpersonationLaunchEntity $portalImpersonationLaunchEntity, public string $rawToken)
    {
    }
}
