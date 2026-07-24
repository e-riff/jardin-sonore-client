<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align the theme UUID column type with the Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE theme CHANGE uuid uuid BINARY(16) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The previous theme UUID column type is unknown.');
    }
}
