<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701113500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise l ancien statut client lead en prospect dans directory_entry.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE directory_entry SET customer_status = 'prospect' WHERE customer_status = 'lead'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE directory_entry SET customer_status = 'lead' WHERE customer_status = 'prospect'");
    }
}
