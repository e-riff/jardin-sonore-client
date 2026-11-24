<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Session\SessionFamily;
use App\Infrastructure\Doctrine\Entity\SessionFamilyEntity;

final readonly class SessionFamilyMapper
{
    public function __construct(private ThemeMapper $themeMapper)
    {
    }

    public function toDomain(SessionFamilyEntity $entity): SessionFamily
    {
        return new SessionFamily($entity->getName(), array_map($this->themeMapper->toDomain(...), $entity->getThemes()->toArray()), $entity->getUuid());
    }
}
