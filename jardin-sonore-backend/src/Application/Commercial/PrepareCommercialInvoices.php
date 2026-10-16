<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoicePreparationEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class PrepareCommercialInvoices
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function run(): int
    {
        $timeZone = new DateTimeZone('Europe/Paris');
        $now = $this->clock->now()->setTimezone($timeZone);
        $currentMonth = new DateTimeImmutable($now->format('Y-m-01'), $timeZone);
        $queryBuilder = $this->entityManager->getRepository(CommercialInterventionEntity::class)->createQueryBuilder('intervention');
        $interventionEntities = $queryBuilder
            ->addSelect('project')
            ->innerJoin('intervention.project', 'project')
            ->andWhere($queryBuilder->expr()->eq('intervention.status', ':confirmed'))
            ->andWhere($queryBuilder->expr()->gte('intervention.startsAt', ':currentMonth'))
            ->setParameter('confirmed', CommercialInterventionEntity::STATUS_CONFIRMED)
            ->setParameter('currentMonth', $currentMonth)
            ->orderBy('intervention.startsAt', 'ASC')
            ->getQuery()->getResult();

        $groups = [];
        foreach ($interventionEntities as $interventionEntity) {
            $serviceMonth = $interventionEntity->getStartsAt()->setTimezone($timeZone)->format('Y-m');
            $key = $interventionEntity->getProject()?->getId() . ':' . $serviceMonth;
            $groups[$key]['project'] = $interventionEntity->getProject();
            $groups[$key]['month'] = new DateTimeImmutable($serviceMonth . '-01', $timeZone);
            $groups[$key]['interventions'][] = $interventionEntity;
        }

        $createdCount = 0;
        foreach ($groups as $group) {
            $projectEntity = $group['project'];
            $serviceMonth = $group['month'];
            $groupInterventions = $group['interventions'];
            $lastInterventionEntity = end($groupInterventions);
            if (null === $projectEntity || !$lastInterventionEntity instanceof CommercialInterventionEntity || $lastInterventionEntity->getEndsAt() > $now) {
                continue;
            }
            $isReliable = true;
            foreach ($groupInterventions as $interventionEntity) {
                if (null !== $interventionEntity->getSyncError() || $interventionEntity->getEndsAt() > $now) {
                    $isReliable = false;
                    break;
                }
            }
            if (!$isReliable) {
                continue;
            }

            $preparationEntity = $this->entityManager->getRepository(CommercialInvoicePreparationEntity::class)->findOneBy([
                'project' => $projectEntity,
                'serviceMonth' => $serviceMonth,
            ]);
            $invoiceEntity = $this->entityManager->getRepository(CommercialInvoiceEntity::class)->findOneBy([
                'project' => $projectEntity,
                'serviceMonth' => $serviceMonth,
            ]);
            if ($invoiceEntity instanceof CommercialInvoiceEntity) {
                if ($preparationEntity instanceof CommercialInvoicePreparationEntity && CommercialActionEntity::STATUS_OPEN === $preparationEntity->getAction()->getStatus()) {
                    $preparationEntity->getAction()->resolve(CommercialActionEntity::STATUS_DONE, $now);
                }
                continue;
            }

            if ($preparationEntity instanceof CommercialInvoicePreparationEntity) {
                if (CommercialActionEntity::STATUS_OPEN === $preparationEntity->getAction()->getStatus()) {
                    $preparationEntity->getAction()->setDueOn($lastInterventionEntity->getStartsAt()->setTimezone($timeZone));
                }
                continue;
            }

            $actionEntity = new CommercialActionEntity(
                $projectEntity,
                'Préparer la facture — ' . $serviceMonth->format('m/Y'),
                $lastInterventionEntity->getStartsAt()->setTimezone($timeZone),
                person: $projectEntity->getPrimaryContact(),
            );
            $projectEntity->addAction($actionEntity);
            $this->entityManager->persist(new CommercialInvoicePreparationEntity($projectEntity, $serviceMonth, $actionEntity));
            ++$createdCount;
        }

        $this->entityManager->flush();

        return $createdCount;
    }
}
