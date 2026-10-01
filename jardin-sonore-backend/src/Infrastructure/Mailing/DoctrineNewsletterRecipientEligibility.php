<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineNewsletterRecipientEligibility implements NewsletterRecipientEligibilityInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function isEligible(string $emailAddress): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $expr = $queryBuilder->expr();

        return 0 < (int) $queryBuilder
            ->select('COUNT(*)')
            ->from('email_contact')
            ->where($expr->eq('LOWER(TRIM(email_address))', ':emailAddress'))
            ->andWhere($expr->eq('active', '1'))
            ->andWhere($expr->eq('opt_in_newsletter', '1'))
            ->andWhere($expr->isNull('unsubscribed_at'))
            ->setParameter('emailAddress', mb_strtolower(trim($emailAddress)))
            ->executeQuery()
            ->fetchOne();
    }
}
