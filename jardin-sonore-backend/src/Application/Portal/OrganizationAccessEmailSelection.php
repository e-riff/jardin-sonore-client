<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\PersonEntity;

final readonly class OrganizationAccessEmailSelection
{
    public function __construct(public string $emailAddress, public ?PersonEntity $personEntity)
    {
    }
}
