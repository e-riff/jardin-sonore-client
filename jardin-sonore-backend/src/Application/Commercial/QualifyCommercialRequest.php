<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectPersonEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DomainException;
use Symfony\Component\Clock\ClockInterface;

final readonly class QualifyCommercialRequest
{
    public function __construct(
        private CommercialProjectWorkflow $projectWorkflow,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<PersonEntity> $people */
    public function qualify(CommercialRequestEntity $requestEntity, OrganizationEntity $organizationEntity, array $people, string $projectTitle, ?PersonEntity $primaryContact = null): CommercialProjectEntity
    {
        if (!$organizationEntity->isActive()) {
            throw new DomainException('An inactive organization cannot receive a new project.');
        }

        $projectEntity = $this->projectWorkflow->qualify($requestEntity, $organizationEntity, $projectTitle);
        foreach ($people as $personEntity) {
            if (!$personEntity->isActive() || $personEntity->getOrganization() !== $organizationEntity) {
                throw new DomainException('The person must be active and belong to the selected organization.');
            }

            $projectEntity->addPerson(new CommercialProjectPersonEntity($projectEntity, $personEntity, $this->clock->now()));
        }

        if (null !== $primaryContact && !in_array($primaryContact, $people, true)) {
            throw new DomainException('The primary contact must be linked to the project.');
        }
        $projectEntity->setPrimaryContact($primaryContact);

        return $projectEntity;
    }
}
