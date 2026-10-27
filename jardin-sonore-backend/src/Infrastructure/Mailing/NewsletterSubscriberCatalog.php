<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class NewsletterSubscriberCatalog
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{uuid: string, email: string, active: bool, opt_in: bool, unsubscribed_at: ?string, free_subscription: bool, confirmed_at: ?string, origin: ?string, confirmation_pending: bool, linked_count: int}>
     */
    public function find(string $query, string $kind, string $status, int $page, int $perPage = 25, string $sort = 'email', string $direction = 'ASC'): array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(
                'LOWER(HEX(contact.uuid)) AS uuid',
                'contact.email_address AS email',
                'contact.active AS active',
                'contact.opt_in_newsletter AS opt_in',
                'contact.unsubscribed_at AS unsubscribed_at',
                'contact.free_newsletter_subscription AS free_subscription',
                'contact.free_newsletter_subscription_confirmed_at AS confirmed_at',
                'contact.free_newsletter_subscription_origin AS origin',
                'EXISTS (SELECT 1 FROM newsletter_subscription_request pending_request WHERE pending_request.email_contact_id = contact.id AND pending_request.consumed_at IS NULL AND pending_request.expires_at > CURRENT_TIMESTAMP AND contact.active = 1 AND contact.opt_in_newsletter = 0 AND contact.unsubscribed_at IS NULL) AS confirmation_pending',
                'COUNT(DISTINCT CASE WHEN linked_entry.id IS NOT NULL THEN email_link.id END) AS linked_count',
            )
            ->from('email_contact', 'contact')
            ->leftJoin('contact', 'contact_details_email_link', 'email_link', 'email_link.email_contact_id = contact.id AND email_link.active = 1')
            ->leftJoin('email_link', 'contact_details', 'linked_details', 'linked_details.id = email_link.contact_details_id')
            ->leftJoin('linked_details', 'directory_entry', 'linked_entry', 'linked_entry.id = linked_details.directory_entry_id AND linked_entry.active = 1')
            ->groupBy('contact.id')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $this->applyFilters($queryBuilder, $query, $kind, $status);

        $hasActiveLink = 'EXISTS (SELECT 1 FROM contact_details_email_link active_link INNER JOIN contact_details active_details ON active_details.id = active_link.contact_details_id INNER JOIN directory_entry active_entry ON active_entry.id = active_details.directory_entry_id WHERE active_link.email_contact_id = contact.id AND active_link.active = 1 AND active_entry.active = 1)';
        $hasPendingRequest = 'EXISTS (SELECT 1 FROM newsletter_subscription_request pending_request WHERE pending_request.email_contact_id = contact.id AND pending_request.consumed_at IS NULL AND pending_request.expires_at > CURRENT_TIMESTAMP AND contact.active = 1 AND contact.opt_in_newsletter = 0 AND contact.unsubscribed_at IS NULL)';
        $sortExpressions = [
            'email' => 'contact.email_address',
            'type' => "CASE WHEN {$hasActiveLink} THEN 0 WHEN contact.free_newsletter_subscription = 1 THEN 1 ELSE 2 END",
            'linked_count' => 'COUNT(DISTINCT CASE WHEN linked_entry.id IS NOT NULL THEN email_link.id END)',
            'status' => "CASE WHEN contact.active = 0 THEN 0 WHEN contact.unsubscribed_at IS NOT NULL THEN 1 WHEN {$hasPendingRequest} THEN 2 WHEN contact.opt_in_newsletter = 0 THEN 3 ELSE 4 END",
        ];
        $queryBuilder->orderBy($sortExpressions[$sort] ?? $sortExpressions['email'], 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC')
            ->addOrderBy('contact.email_address', 'ASC')
            ->addOrderBy('contact.id', 'ASC');

        /** @var list<array{uuid: string, email: string, active: int|string, opt_in: int|string, unsubscribed_at: ?string, free_subscription: int|string, confirmed_at: ?string, origin: ?string, confirmation_pending: int|string, linked_count: int|string}> $rows */
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'uuid' => $row['uuid'],
            'email' => $row['email'],
            'active' => (bool) $row['active'],
            'opt_in' => (bool) $row['opt_in'],
            'unsubscribed_at' => $row['unsubscribed_at'],
            'free_subscription' => (bool) $row['free_subscription'],
            'confirmed_at' => $row['confirmed_at'],
            'origin' => $row['origin'],
            'confirmation_pending' => (bool) $row['confirmation_pending'],
            'linked_count' => (int) $row['linked_count'],
        ], $rows);
    }

    public function count(string $query, string $kind, string $status): int
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('email_contact', 'contact');

        $this->applyFilters($queryBuilder, $query, $kind, $status);

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    public function hasPendingConfirmation(int $emailContactId): bool
    {
        return 1 === (int) $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('newsletter_subscription_request', 'pending_request')
            ->innerJoin('pending_request', 'email_contact', 'contact', 'contact.id = pending_request.email_contact_id')
            ->where('pending_request.email_contact_id = :emailContactId')
            ->andWhere('pending_request.consumed_at IS NULL')
            ->andWhere('pending_request.expires_at > CURRENT_TIMESTAMP')
            ->andWhere('contact.active = 1')
            ->andWhere('contact.opt_in_newsletter = 0')
            ->andWhere('contact.unsubscribed_at IS NULL')
            ->setParameter('emailContactId', $emailContactId)
            ->executeQuery()
            ->fetchOne();
    }

    private function applyFilters(QueryBuilder $queryBuilder, string $query, string $kind, string $status): void
    {
        $hasActiveLink = 'EXISTS (SELECT 1 FROM contact_details_email_link active_link INNER JOIN contact_details active_details ON active_details.id = active_link.contact_details_id INNER JOIN directory_entry active_entry ON active_entry.id = active_details.directory_entry_id WHERE active_link.email_contact_id = contact.id AND active_link.active = 1 AND active_entry.active = 1)';
        $hasPendingRequest = 'EXISTS (SELECT 1 FROM newsletter_subscription_request pending_request WHERE pending_request.email_contact_id = contact.id AND pending_request.consumed_at IS NULL AND pending_request.expires_at > CURRENT_TIMESTAMP AND contact.active = 1 AND contact.opt_in_newsletter = 0 AND contact.unsubscribed_at IS NULL)';

        if ('' !== $query) {
            $queryBuilder->andWhere('contact.email_address LIKE :query')->setParameter('query', '%' . $query . '%');
        }

        if ('linked' === $kind) {
            $queryBuilder->andWhere($hasActiveLink);
        } elseif ('free' === $kind) {
            $queryBuilder->andWhere('contact.free_newsletter_subscription = 1')->andWhere('NOT ' . $hasActiveLink);
        }

        match ($status) {
            'subscribed' => $queryBuilder->andWhere('contact.active = 1')->andWhere('contact.opt_in_newsletter = 1')->andWhere('contact.unsubscribed_at IS NULL')->andWhere('NOT ' . $hasPendingRequest),
            'not_subscribed' => $queryBuilder->andWhere('contact.active = 1')->andWhere('contact.opt_in_newsletter = 0')->andWhere('contact.unsubscribed_at IS NULL')->andWhere('NOT ' . $hasPendingRequest),
            'unsubscribed' => $queryBuilder->andWhere('contact.active = 1')->andWhere('contact.unsubscribed_at IS NOT NULL'),
            'inactive' => $queryBuilder->andWhere('contact.active = 0'),
            'pending' => $queryBuilder->andWhere($hasPendingRequest),
            default => null,
        };
    }
}
