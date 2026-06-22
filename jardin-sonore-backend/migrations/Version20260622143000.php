<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add municipality center coordinates for newsletter radius targeting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE municipality ADD center_latitude DOUBLE PRECISION DEFAULT NULL, ADD center_longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_municipality_center ON municipality (center_latitude, center_longitude)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_municipality_center ON municipality');
        $this->addSql('ALTER TABLE municipality DROP center_latitude, DROP center_longitude');
    }
}
