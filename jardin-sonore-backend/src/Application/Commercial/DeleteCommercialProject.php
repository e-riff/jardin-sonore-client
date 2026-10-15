<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

final class DeleteCommercialProject
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function canDelete(CommercialProjectEntity $projectEntity): bool
    {
        return null === $projectEntity->getRequest()
            && $projectEntity->getQuotes()->isEmpty()
            && $projectEntity->getInvoices()->isEmpty();
    }

    public function delete(CommercialProjectEntity $projectEntity): void
    {
        if (!$this->canDelete($projectEntity)) {
            throw new DomainException('This project cannot be deleted.');
        }

        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($projectEntity): void {
            foreach ($projectEntity->getEvents() as $eventEntity) {
                $entityManager->remove($eventEntity);
            }
            $entityManager->flush();

            foreach ($projectEntity->getActions() as $actionEntity) {
                $entityManager->remove($actionEntity);
            }
            foreach ($projectEntity->getPeople() as $projectPersonEntity) {
                $entityManager->remove($projectPersonEntity);
            }
            $entityManager->remove($projectEntity);
        });
    }
}
