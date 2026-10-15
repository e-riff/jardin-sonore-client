<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteCommercialProject
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function delete(CommercialProjectEntity $projectEntity): void
    {
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($projectEntity): void {
            $projectEntity->getRequest()?->removeProject($projectEntity);

            foreach ($projectEntity->getEvents() as $eventEntity) {
                $entityManager->remove($eventEntity);
            }
            $entityManager->flush();

            foreach ($projectEntity->getQuotes() as $quoteEntity) {
                $quoteEntity->setReplaces(null);
            }
            $entityManager->flush();

            foreach ($projectEntity->getQuotes() as $quoteEntity) {
                $entityManager->remove($quoteEntity);
            }
            foreach ($projectEntity->getInvoices() as $invoiceEntity) {
                $entityManager->remove($invoiceEntity);
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
