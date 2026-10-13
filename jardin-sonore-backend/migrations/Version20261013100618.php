<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize legacy unknown customer statuses added after the nullable enum migration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE directory_entry SET customer_status = NULL WHERE customer_status = 'unknown'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The previous NULL customer statuses cannot be distinguished from normalized legacy values.');
    }
}
