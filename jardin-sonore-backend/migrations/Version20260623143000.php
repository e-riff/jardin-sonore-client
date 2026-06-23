<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize phone number formatting in address book and municipalities.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE phone_contact CHANGE phone_number phone_number VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE municipality CHANGE phone_number phone_number VARCHAR(32) DEFAULT NULL');

        $this->addSql($this->normalizePhoneNumbersSql('phone_contact', 'phone_number'));
        $this->addSql($this->normalizePhoneNumbersSql('municipality', 'phone_number'));
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE phone_contact CHANGE phone_number phone_number VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE municipality CHANGE phone_number phone_number VARCHAR(20) DEFAULT NULL');
    }

    private function normalizePhoneNumbersSql(string $tableName, string $columnName): string
    {
        $digits = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$columnName}, ' ', ''), '.', ''), '-', ''), '(', ''), ')', '')";
        $spaced = "TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$columnName}, ' ', ' '), '.', ' '), '-', ' '), '(', ' '), ')', ' '))";

        return <<<SQL
UPDATE {$tableName}
SET {$columnName} = CASE
    WHEN CHAR_LENGTH({$digits}) = 10 AND {$digits} LIKE '0%' THEN CONCAT(SUBSTRING({$digits}, 1, 2), ' ', SUBSTRING({$digits}, 3, 2), ' ', SUBSTRING({$digits}, 5, 2), ' ', SUBSTRING({$digits}, 7, 2), ' ', SUBSTRING({$digits}, 9, 2))
    WHEN CHAR_LENGTH({$digits}) = 13 AND {$digits} LIKE '0033%' THEN CONCAT('+33 ', SUBSTRING({$digits}, 5, 1), ' ', SUBSTRING({$digits}, 6, 2), ' ', SUBSTRING({$digits}, 8, 2), ' ', SUBSTRING({$digits}, 10, 2), ' ', SUBSTRING({$digits}, 12, 2))
    WHEN CHAR_LENGTH({$digits}) = 12 AND {$digits} LIKE '+33%' THEN CONCAT('+33 ', SUBSTRING({$digits}, 4, 1), ' ', SUBSTRING({$digits}, 5, 2), ' ', SUBSTRING({$digits}, 7, 2), ' ', SUBSTRING({$digits}, 9, 2), ' ', SUBSTRING({$digits}, 11, 2))
    ELSE {$spaced}
END
WHERE {$columnName} IS NOT NULL AND {$columnName} <> ''
SQL;
    }
}
