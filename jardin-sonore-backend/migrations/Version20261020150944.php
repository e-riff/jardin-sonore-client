<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020150944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional indicative time to commercial dossier tasks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_action ADD due_time VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_action DROP due_time');
    }
}
