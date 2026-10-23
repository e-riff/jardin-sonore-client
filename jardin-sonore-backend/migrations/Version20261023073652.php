<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023073652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused media secondary URL and repertoire notes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_resource DROP secondary_url');
        $this->addSql('ALTER TABLE repertoire_item DROP notes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_resource ADD secondary_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE repertoire_item ADD notes LONGTEXT DEFAULT NULL');
    }
}
