<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use App\Domain\Model\Mailing\MailingCampaignType;
use Doctrine\DBAL\Connection;

final readonly class DoctrineNewsletterRecipientEligibility implements NewsletterRecipientEligibilityInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function isEligible(string $emailAddress, ?MailingCampaignType $campaignType = null): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $expr = $queryBuilder->expr();

        if (null === $campaignType) {
            $queryBuilder->andWhere('opt_in_newsletter = 1 AND unsubscribed_at IS NULL AND newsletter_refused = 0 AND outreach_refused = 0 AND (directory_mailing_allowed = 1 OR (free_newsletter_subscription = 1 AND free_newsletter_subscription_confirmed_at IS NOT NULL))');
        } elseif (MailingCampaignType::NEWSLETTER === $campaignType) {
            $queryBuilder->andWhere('newsletter_refused = 0 AND (directory_mailing_allowed = 1 OR (free_newsletter_subscription = 1 AND free_newsletter_subscription_confirmed_at IS NOT NULL))');
        } else {
            $queryBuilder->andWhere('outreach_refused = 0 AND directory_mailing_allowed = 1');
        }

        return 0 < (int) $queryBuilder
            ->select('COUNT(*)')
            ->from('email_contact')
            ->andWhere($expr->eq('LOWER(TRIM(email_address))', ':emailAddress'))
            ->andWhere($expr->eq('active', '1'))
            ->setParameter('emailAddress', mb_strtolower(trim($emailAddress)))
            ->executeQuery()
            ->fetchOne();
    }
}
