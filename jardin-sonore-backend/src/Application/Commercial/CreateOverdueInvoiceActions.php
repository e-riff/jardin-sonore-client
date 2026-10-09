<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class CreateOverdueInvoiceActions
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RecordCommercialInvoice $recorder,
        private ClockInterface $clock,
    ) {
    }

    public function run(?int $invoiceId = null): int
    {
        $threshold = $this->clock->now()->setTime(0, 0)->modify('-30 days');
        $queryBuilder = $this->entityManager->getRepository(CommercialInvoiceEntity::class)->createQueryBuilder('invoice');
        $queryBuilder
            ->select('invoice.id')
            ->andWhere($queryBuilder->expr()->isNull('invoice.paidOn'))
            ->andWhere($queryBuilder->expr()->isNull('invoice.reminderAction'))
            ->andWhere($queryBuilder->expr()->lte('invoice.issuedOn', ':threshold'))
            ->setParameter('threshold', $threshold, Types::DATE_IMMUTABLE);
        if (null !== $invoiceId) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('invoice.id', ':invoiceId'))->setParameter('invoiceId', $invoiceId);
        }

        $created = 0;
        foreach ($queryBuilder->getQuery()->getScalarResult() as $row) {
            /** @var array{id: int|string} $row */
            $created += $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($row): int {
                $invoiceEntity = $entityManager->find(CommercialInvoiceEntity::class, (int) $row['id']);
                if (!$invoiceEntity instanceof CommercialInvoiceEntity) {
                    return 0;
                }
                $entityManager->refresh($invoiceEntity, LockMode::PESSIMISTIC_WRITE);

                return $this->recorder->createReminderIfOverdue($invoiceEntity) ? 1 : 0;
            });
        }

        return $created;
    }
}
