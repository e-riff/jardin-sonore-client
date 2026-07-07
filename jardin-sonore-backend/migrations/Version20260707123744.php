<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707123744 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronize existing Doctrine schema drift.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_phone_contact_phone_number ON phone_contact');
        $this->addSql('ALTER TABLE mailing_campaign CHANGE audience_filter audience_filter JSON NOT NULL');
        $this->addSql('ALTER TABLE municipality CHANGE geo_shape geo_shape JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_phone_contact_phone_number ON phone_contact (phone_number)');
        $this->addSql('ALTER TABLE mailing_campaign CHANGE audience_filter audience_filter LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE municipality CHANGE geo_shape geo_shape LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
    }
}
