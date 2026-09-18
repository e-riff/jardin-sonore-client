<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use LogicException;

final class PortalAccessManager
{
    public function removeAccess(UserEntity $userEntity, UserOrganizationAccessEntity $userOrganizationAccessEntity): void
    {
        if (1 >= $userEntity->getOrganizationAccesses()->count()) {
            throw new LogicException('A portal account must retain at least one organization access.');
        }

        $userEntity->removeOrganizationAccess($userOrganizationAccessEntity);
    }
}
