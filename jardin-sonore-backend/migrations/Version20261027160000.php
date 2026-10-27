<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache le secteur historique du Ludobus à la commune de Saint-Chamond pour le ciblage mailing.';
    }

    public function up(Schema $schema): void
    {
        $targetCount = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM address_contact
            WHERE BINARY label = BINARY 'Secteur desservi'
                AND BINARY address = BINARY 'Pays du Gier (18 communes)'
                AND municipality_id IS NULL
            SQL);
        $municipalityCount = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM municipality
            WHERE BINARY insee_code = BINARY '42207'
            SQL);

        $this->abortIf(1 !== $targetCount, sprintf('Expected one unlinked Ludobus coverage address, found %d.', $targetCount));
        $this->abortIf(1 !== $municipalityCount, sprintf('Expected one Saint-Chamond municipality (INSEE 42207), found %d.', $municipalityCount));

        $this->addSql(<<<'SQL'
            UPDATE address_contact
            SET municipality_id = (
                SELECT id
                FROM municipality
                WHERE BINARY insee_code = BINARY '42207'
            )
            WHERE BINARY label = BINARY 'Secteur desservi'
                AND BINARY address = BINARY 'Pays du Gier (18 communes)'
                AND municipality_id IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE address_contact
            SET municipality_id = NULL
            WHERE BINARY label = BINARY 'Secteur desservi'
                AND BINARY address = BINARY 'Pays du Gier (18 communes)'
                AND municipality_id = (
                    SELECT id
                    FROM municipality
                    WHERE BINARY insee_code = BINARY '42207'
                )
            SQL);
    }
}
