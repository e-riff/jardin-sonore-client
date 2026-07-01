<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise les chaînes vides des communes en NULL pour address, siren et siret.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE municipality SET address = NULL WHERE address IS NOT NULL AND TRIM(address) = ''");
        $this->addSql("UPDATE municipality SET siren = NULL WHERE siren IS NOT NULL AND TRIM(siren) = ''");
        $this->addSql("UPDATE municipality SET siret = NULL WHERE siret IS NOT NULL AND TRIM(siret) = ''");
    }

    public function down(Schema $schema): void
    {
    }
}
