<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BuildCommercialDigest
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function forDate(DateTimeImmutable $localDate): CommercialDigest
    {
        $requests = $this->entityManager->getRepository(CommercialRequestEntity::class)->findBy(
            ['status' => CommercialRequestEntity::STATUS_TO_QUALIFY],
            ['receivedAt' => 'ASC'],
        );
        $actionQueryBuilder = $this->entityManager->getRepository(CommercialActionEntity::class)->createQueryBuilder('action');
        $actions = $actionQueryBuilder
            ->andWhere($actionQueryBuilder->expr()->eq('action.status', ':status'))
            ->andWhere($actionQueryBuilder->expr()->lte('action.dueOn', ':today'))
            ->setParameter('status', CommercialActionEntity::STATUS_OPEN)
            ->setParameter('today', $localDate, Types::DATE_IMMUTABLE)
            ->orderBy('action.dueOn', 'ASC')
            ->getQuery()->getResult();

        $invoiceQueryBuilder = $this->entityManager->getRepository(CommercialInvoiceEntity::class)->createQueryBuilder('invoice');
        $overdueInvoices = $invoiceQueryBuilder
            ->andWhere($invoiceQueryBuilder->expr()->isNull('invoice.paidOn'))
            ->andWhere($invoiceQueryBuilder->expr()->lte('invoice.issuedOn', ':threshold'))
            ->setParameter('threshold', $localDate->modify('-30 days'), Types::DATE_IMMUTABLE)
            ->orderBy('invoice.issuedOn', 'ASC')
            ->getQuery()->getResult();

        $invoiceReminderIds = [];
        foreach ($overdueInvoices as $invoiceEntity) {
            $reminderId = $invoiceEntity->getReminderAction()?->getId();
            if (null !== $reminderId) {
                $invoiceReminderIds[] = $reminderId;
            }
        }
        $dueActions = array_values(array_filter(
            $actions,
            static fn (CommercialActionEntity $actionEntity): bool => !in_array($actionEntity->getId(), $invoiceReminderIds, true),
        ));

        $withoutAction = [];
        if ('1' === $localDate->format('N')) {
            $projects = $this->entityManager->getRepository(CommercialProjectEntity::class)->findBy([
                'status' => [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED],
            ]);
            foreach ($projects as $projectEntity) {
                $hasOpenAction = false;
                foreach ($projectEntity->getActions() as $actionEntity) {
                    if (CommercialActionEntity::STATUS_OPEN === $actionEntity->getStatus()) {
                        $hasOpenAction = true;
                        break;
                    }
                }
                if (!$hasOpenAction) {
                    $withoutAction[] = $projectEntity;
                }
            }
        }

        return new CommercialDigest($localDate, $requests, $dueActions, $overdueInvoices, $withoutAction);
    }
}
