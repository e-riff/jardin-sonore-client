<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Qualify empty organization types from email local parts containing creche, microcreche or relais.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
UPDATE organization
SET type = 'creche'
WHERE type IS NULL
AND EXISTS (
    SELECT 1
    FROM directory_entry entry
    INNER JOIN contact_details contact ON contact.directory_entry_id = entry.id
    INNER JOIN email_contact email ON email.contact_details_id = contact.id
    WHERE entry.id = organization.id
    AND LOWER(SUBSTRING_INDEX(email.email_address, '@', 1)) LIKE '%creche%'
)
SQL);

        $this->addSql(<<<'SQL'
UPDATE organization
SET type = 'ram'
WHERE type IS NULL
AND EXISTS (
    SELECT 1
    FROM directory_entry entry
    INNER JOIN contact_details contact ON contact.directory_entry_id = entry.id
    INNER JOIN email_contact email ON email.contact_details_id = contact.id
    WHERE entry.id = organization.id
    AND LOWER(SUBSTRING_INDEX(email.email_address, '@', 1)) LIKE '%relais%'
)
SQL);

        $this->addSql(<<<'SQL'
UPDATE organization
INNER JOIN person ON person.organization_id = organization.id
SET organization.type = 'creche'
WHERE organization.type IS NULL
AND EXISTS (
    SELECT 1
    FROM directory_entry entry
    INNER JOIN contact_details contact ON contact.directory_entry_id = entry.id
    INNER JOIN email_contact email ON email.contact_details_id = contact.id
    WHERE entry.id = person.id
    AND LOWER(SUBSTRING_INDEX(email.email_address, '@', 1)) LIKE '%creche%'
)
SQL);

        $this->addSql(<<<'SQL'
UPDATE organization
INNER JOIN person ON person.organization_id = organization.id
SET organization.type = 'ram'
WHERE organization.type IS NULL
AND EXISTS (
    SELECT 1
    FROM directory_entry entry
    INNER JOIN contact_details contact ON contact.directory_entry_id = entry.id
    INNER JOIN email_contact email ON email.contact_details_id = contact.id
    WHERE entry.id = person.id
    AND LOWER(SUBSTRING_INDEX(email.email_address, '@', 1)) LIKE '%relais%'
)
SQL);
    }

    public function down(Schema $schema): void
    {
    }
}
