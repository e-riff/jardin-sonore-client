<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730130311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds public general instructions to repertoire items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repertoire_item ADD general_instructions LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repertoire_item DROP general_instructions');
    }
}
