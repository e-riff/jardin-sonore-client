<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127083953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identify the unique reserved newsletter audience mask without changing existing masks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_audience_mask ADD reserved_role VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_mailing_audience_mask_role ON mailing_audience_mask (reserved_role)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_mailing_audience_mask_role ON mailing_audience_mask');
        $this->addSql('ALTER TABLE mailing_audience_mask DROP reserved_role');
    }
}
