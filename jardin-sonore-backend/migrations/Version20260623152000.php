<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store phone numbers without visual separators.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql($this->normalizePhoneNumbersSql('phone_contact', 'phone_number'));
        $this->addSql($this->normalizePhoneNumbersSql('municipality', 'phone_number'));
    }

    public function down(Schema $schema): void
    {
    }

    private function normalizePhoneNumbersSql(string $tableName, string $columnName): string
    {
        $compactPhoneNumber = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$columnName}, ' ', ''), '.', ''), '-', ''), '(', ''), ')', '')";

        return <<<SQL
UPDATE {$tableName}
SET {$columnName} = CASE
    WHEN CHAR_LENGTH({$compactPhoneNumber}) = 10 AND {$compactPhoneNumber} LIKE '0%' THEN CONCAT('+33', SUBSTRING({$compactPhoneNumber}, 2))
    WHEN CHAR_LENGTH({$compactPhoneNumber}) >= 8 AND {$compactPhoneNumber} LIKE '00%' THEN CONCAT('+', SUBSTRING({$compactPhoneNumber}, 3))
    ELSE {$compactPhoneNumber}
END
WHERE {$columnName} IS NOT NULL AND {$columnName} <> ''
SQL;
    }
}
