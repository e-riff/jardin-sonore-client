<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260701082837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add timestamps to directory entities, support shared email and phone contacts, and add directory import tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addTimestampColumns('address_contact');
        $this->addTimestampColumns('contact_details');
        $this->addTimestampColumns('directory_entry');
        $this->addTimestampColumns('email_contact');
        $this->addTimestampColumns('phone_contact');
        $this->addTimestampColumns('tag');

        if (!$this->columnExists('organization', 'website_url')) {
            $this->connection->executeStatement('ALTER TABLE organization ADD website_url VARCHAR(2048) DEFAULT NULL');
        }

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS contact_details_email_link (
              id INT AUTO_INCREMENT NOT NULL,
              uuid BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              label VARCHAR(255) DEFAULT NULL,
              type VARCHAR(32) NOT NULL,
              active TINYINT DEFAULT 1 NOT NULL,
              contact_details_id INT NOT NULL,
              email_contact_id INT NOT NULL,
              INDEX idx_contact_details_email_link_contact_details (contact_details_id),
              INDEX idx_contact_details_email_link_email_contact (email_contact_id),
              UNIQUE INDEX uniq_contact_details_email_link_uuid (uuid),
              UNIQUE INDEX uniq_contact_details_email_link_pair (contact_details_id, email_contact_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        if (!$this->foreignKeyExists('contact_details_email_link', 'FK_4D19D5DE7CA35EB5')) {
            $this->connection->executeStatement('ALTER TABLE contact_details_email_link ADD CONSTRAINT FK_4D19D5DE7CA35EB5 FOREIGN KEY (contact_details_id) REFERENCES contact_details (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('contact_details_email_link', 'FK_4D19D5DE4B12D795')) {
            $this->connection->executeStatement('ALTER TABLE contact_details_email_link ADD CONSTRAINT FK_4D19D5DE4B12D795 FOREIGN KEY (email_contact_id) REFERENCES email_contact (id) ON DELETE CASCADE');
        }

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS contact_details_phone_link (
              id INT AUTO_INCREMENT NOT NULL,
              uuid BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              label VARCHAR(255) DEFAULT NULL,
              type VARCHAR(32) NOT NULL,
              active TINYINT DEFAULT 1 NOT NULL,
              contact_details_id INT NOT NULL,
              phone_contact_id INT NOT NULL,
              INDEX idx_contact_details_phone_link_contact_details (contact_details_id),
              INDEX idx_contact_details_phone_link_phone_contact (phone_contact_id),
              UNIQUE INDEX uniq_contact_details_phone_link_uuid (uuid),
              UNIQUE INDEX uniq_contact_details_phone_link_pair (contact_details_id, phone_contact_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        if (!$this->foreignKeyExists('contact_details_phone_link', 'FK_312BA7497CA35EB5')) {
            $this->connection->executeStatement('ALTER TABLE contact_details_phone_link ADD CONSTRAINT FK_312BA7497CA35EB5 FOREIGN KEY (contact_details_id) REFERENCES contact_details (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('contact_details_phone_link', 'FK_312BA749CFAAE3DF')) {
            $this->connection->executeStatement('ALTER TABLE contact_details_phone_link ADD CONSTRAINT FK_312BA749CFAAE3DF FOREIGN KEY (phone_contact_id) REFERENCES phone_contact (id) ON DELETE CASCADE');
        }

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS directory_import_link (
              id INT AUTO_INCREMENT NOT NULL,
              uuid BINARY(16) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              source VARCHAR(64) NOT NULL,
              external_id VARCHAR(255) NOT NULL,
              external_organization_id VARCHAR(255) DEFAULT NULL,
              payload_hash VARCHAR(64) NOT NULL,
              directory_entry_id INT NOT NULL,
              INDEX idx_directory_import_link_directory_entry (directory_entry_id),
              INDEX idx_directory_import_link_source (source),
              UNIQUE INDEX uniq_directory_import_link_uuid (uuid),
              UNIQUE INDEX uniq_directory_import_link_source_external_id (source, external_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        if (!$this->foreignKeyExists('directory_import_link', 'FK_1D31CD8EBE8E7CAF')) {
            $this->connection->executeStatement('ALTER TABLE directory_import_link ADD CONSTRAINT FK_1D31CD8EBE8E7CAF FOREIGN KEY (directory_entry_id) REFERENCES directory_entry (id) ON DELETE CASCADE');
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->executeStatement('DELETE FROM contact_details_email_link');
        $this->connection->executeStatement('DELETE FROM contact_details_phone_link');

        /** @var list<array{id:int,contact_details_id:int,label:?string,type:string,active:bool}> $emailRows */
        $emailRows = $this->connection->fetchAllAssociative(
            'SELECT id, contact_details_id, label, type, active FROM email_contact WHERE contact_details_id IS NOT NULL',
        );

        foreach ($emailRows as $emailRow) {
            $this->connection->insert('contact_details_email_link', [
                'uuid' => Uuid::v7()->toBinary(),
                'contact_details_id' => (int) $emailRow['contact_details_id'],
                'email_contact_id' => (int) $emailRow['id'],
                'created_at' => $now,
                'updated_at' => $now,
                'label' => $emailRow['label'],
                'type' => $emailRow['type'],
                'active' => (int) (!empty($emailRow['active'])),
            ], [
                'uuid' => ParameterType::BINARY,
            ]);
        }

        /** @var list<array{id:int,contact_details_id:int,label:?string,type:string,active:bool}> $phoneRows */
        $phoneRows = $this->connection->fetchAllAssociative(
            'SELECT id, contact_details_id, label, type, active FROM phone_contact WHERE contact_details_id IS NOT NULL',
        );

        foreach ($phoneRows as $phoneRow) {
            $this->connection->insert('contact_details_phone_link', [
                'uuid' => Uuid::v7()->toBinary(),
                'contact_details_id' => (int) $phoneRow['contact_details_id'],
                'phone_contact_id' => (int) $phoneRow['id'],
                'created_at' => $now,
                'updated_at' => $now,
                'label' => $phoneRow['label'],
                'type' => $phoneRow['type'],
                'active' => (int) (!empty($phoneRow['active'])),
            ], [
                'uuid' => ParameterType::BINARY,
            ]);
        }

        if ($this->foreignKeyExists('email_contact', 'FK_EMAIL_CONTACT_CONTACT_DETAILS')) {
            $this->connection->executeStatement('ALTER TABLE email_contact DROP FOREIGN KEY `FK_EMAIL_CONTACT_CONTACT_DETAILS`');
        }
        if ($this->indexExists('email_contact', 'idx_email_contact_details')) {
            $this->connection->executeStatement('DROP INDEX idx_email_contact_details ON email_contact');
        }
        if ($this->columnExists('email_contact', 'label')) {
            $this->connection->executeStatement('ALTER TABLE email_contact DROP label');
        }
        if ($this->columnExists('email_contact', 'contact_details_id')) {
            $this->connection->executeStatement('ALTER TABLE email_contact DROP contact_details_id');
        }
        if ($this->columnExists('email_contact', 'type')) {
            $this->connection->executeStatement('ALTER TABLE email_contact DROP type');
        }

        if ($this->foreignKeyExists('phone_contact', 'FK_PHONE_CONTACT_CONTACT_DETAILS')) {
            $this->connection->executeStatement('ALTER TABLE phone_contact DROP FOREIGN KEY `FK_PHONE_CONTACT_CONTACT_DETAILS`');
        }
        if ($this->indexExists('phone_contact', 'idx_phone_contact_details')) {
            $this->connection->executeStatement('DROP INDEX idx_phone_contact_details ON phone_contact');
        }
        if ($this->columnExists('phone_contact', 'label')) {
            $this->connection->executeStatement('ALTER TABLE phone_contact DROP label');
        }
        if ($this->columnExists('phone_contact', 'contact_details_id')) {
            $this->connection->executeStatement('ALTER TABLE phone_contact DROP contact_details_id');
        }
        if ($this->columnExists('phone_contact', 'type')) {
            $this->connection->executeStatement('ALTER TABLE phone_contact DROP type');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration copies legacy contact metadata into shared link tables and cannot be safely reversed automatically.');
    }

    private function addTimestampColumns(string $tableName): void
    {
        if (!$this->columnExists($tableName, 'created_at')) {
            $this->connection->executeStatement(sprintf('ALTER TABLE %s ADD created_at DATETIME DEFAULT NULL', $tableName));
        }

        if (!$this->columnExists($tableName, 'updated_at')) {
            $this->connection->executeStatement(sprintf('ALTER TABLE %s ADD updated_at DATETIME DEFAULT NULL', $tableName));
        }

        $this->connection->executeStatement(sprintf('UPDATE %s SET created_at = COALESCE(created_at, NOW()), updated_at = COALESCE(updated_at, NOW())', $tableName));
        $this->connection->executeStatement(sprintf('ALTER TABLE %s MODIFY created_at DATETIME NOT NULL, MODIFY updated_at DATETIME NOT NULL', $tableName));
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        return (bool) $this->connection->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tableName
                AND COLUMN_NAME = :columnName
                LIMIT 1
            SQL,
            [
                'tableName' => $tableName,
                'columnName' => $columnName,
            ],
        );
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return (bool) $this->connection->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tableName
                AND CONSTRAINT_NAME = :constraintName
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                LIMIT 1
            SQL,
            [
                'tableName' => $tableName,
                'constraintName' => $constraintName,
            ],
        );
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
