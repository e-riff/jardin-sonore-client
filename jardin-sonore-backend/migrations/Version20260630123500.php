<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630123500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subtitle, call to action and banner image fields to mailing campaigns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign ADD subtitle VARCHAR(255) DEFAULT NULL, ADD cta_label VARCHAR(255) DEFAULT NULL, ADD cta_url VARCHAR(2048) DEFAULT NULL, ADD banner_image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign DROP subtitle, DROP cta_label, DROP cta_url, DROP banner_image_path');
    }
}
