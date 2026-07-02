<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional tag to newsletter and mailing recommendations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_recommendation ADD tag VARCHAR(40) DEFAULT NULL AFTER title');
        $this->addSql('ALTER TABLE mailing_recommendation ADD tag VARCHAR(40) DEFAULT NULL AFTER title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_recommendation DROP tag');
        $this->addSql('ALTER TABLE mailing_recommendation DROP tag');
    }
}
