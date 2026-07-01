<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Model\ValueObject\PhoneNumber;
use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use InvalidArgumentException;

final class Version20260701130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize phone contacts, merge duplicate phone numbers, and enforce phone number uniqueness.';
    }

    public function up(Schema $schema): void
    {
        $this->normalizePhoneNumbers();
        $this->mergeDuplicatePhoneContacts();

        if (!$this->indexExists('phone_contact', 'uniq_phone_contact_phone_number')) {
            $this->addSql('ALTER TABLE phone_contact ADD CONSTRAINT uniq_phone_contact_phone_number UNIQUE (phone_number)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->indexExists('phone_contact', 'uniq_phone_contact_phone_number')) {
            $this->addSql('ALTER TABLE phone_contact DROP INDEX uniq_phone_contact_phone_number');
        }
    }

    private function normalizePhoneNumbers(): void
    {
        /** @var list<array{id:int, phone_number:string}> $phoneContacts */
        $phoneContacts = $this->connection->fetchAllAssociative('SELECT id, phone_number FROM phone_contact ORDER BY id ASC');

        foreach ($phoneContacts as $phoneContact) {
            $normalizedPhoneNumber = $this->normalizePhoneNumber($phoneContact['phone_number']);

            if (null === $normalizedPhoneNumber || $normalizedPhoneNumber === $phoneContact['phone_number']) {
                continue;
            }

            $this->connection->update('phone_contact', [
                'phone_number' => $normalizedPhoneNumber,
            ], [
                'id' => (int) $phoneContact['id'],
            ]);
        }
    }

    private function mergeDuplicatePhoneContacts(): void
    {
        /** @var list<array{id:int, phone_number:string}> $phoneContacts */
        $phoneContacts = $this->connection->fetchAllAssociative('SELECT id, phone_number FROM phone_contact ORDER BY phone_number ASC, id ASC');
        $survivorIdsByPhoneNumber = [];
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($phoneContacts as $phoneContact) {
            $phoneContactId = (int) $phoneContact['id'];
            $phoneNumber = (string) $phoneContact['phone_number'];

            if (!isset($survivorIdsByPhoneNumber[$phoneNumber])) {
                $survivorIdsByPhoneNumber[$phoneNumber] = $phoneContactId;

                continue;
            }

            $survivorId = $survivorIdsByPhoneNumber[$phoneNumber];

            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE contact_details_phone_link duplicate_link
                    LEFT JOIN contact_details_phone_link survivor_link
                        ON survivor_link.contact_details_id = duplicate_link.contact_details_id
                        AND survivor_link.phone_contact_id = :survivorId
                    SET duplicate_link.phone_contact_id = :survivorId,
                        duplicate_link.updated_at = :updatedAt
                    WHERE duplicate_link.phone_contact_id = :duplicateId
                      AND survivor_link.id IS NULL
                SQL,
                [
                    'survivorId' => $survivorId,
                    'duplicateId' => $phoneContactId,
                    'updatedAt' => $now,
                ],
            );

            $this->connection->delete('contact_details_phone_link', [
                'phone_contact_id' => $phoneContactId,
            ]);

            $this->connection->delete('phone_contact', [
                'id' => $phoneContactId,
            ]);
        }
    }

    private function normalizePhoneNumber(string $phoneNumber): ?string
    {
        $phoneNumber = trim($phoneNumber);

        if ('' === $phoneNumber) {
            return null;
        }

        try {
            return PhoneNumber::normalize($phoneNumber);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return (bool) $this->connection->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :tableName
                  AND INDEX_NAME = :indexName
                LIMIT 1
            SQL,
            [
                'tableName' => $tableName,
                'indexName' => $indexName,
            ],
        );
    }
}
