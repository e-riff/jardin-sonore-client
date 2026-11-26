<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\MailingCampaignDeliveryTransactionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final readonly class DoctrineMailingCampaignDeliveryTransaction implements MailingCampaignDeliveryTransactionInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function run(callable $operation): mixed
    {
        try {
            return $this->entityManager->getConnection()->transactional(static fn (): mixed => $operation());
        } catch (Throwable $throwable) {
            $this->entityManager->clear();
            throw $throwable;
        }
    }
}
