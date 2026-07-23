<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add colors to instrument categories and shared colored themes for repertoire and media resources.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE instrument_tag ADD color VARCHAR(7) NOT NULL DEFAULT '#64748b'");
        $this->addSql("UPDATE instrument_tag SET color = CASE LOWER(label) WHEN 'accessoire' THEN '#64748b' WHEN 'bois' THEN '#a16207' WHEN 'cordes' THEN '#7c3aed' WHEN 'instrument' THEN '#2563eb' WHEN 'mélodique' THEN '#0891b2' WHEN 'métal' THEN '#475569' WHEN 'peau' THEN '#b45309' WHEN 'percussion' THEN '#dc2626' WHEN 'pvc' THEN '#0f766e' WHEN 'sensoriel' THEN '#db2777' WHEN 'végétal' THEN '#65a30d' WHEN 'vent' THEN '#0284c7' WHEN 'verre' THEN '#06b6d4' ELSE '#64748b' END");
        $this->addSql('CREATE TABLE theme (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', label VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL, INDEX idx_theme_label (label), UNIQUE INDEX uniq_theme_uuid (uuid), UNIQUE INDEX uniq_theme_label (label), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE repertoire_item_theme (repertoire_item_id INT NOT NULL, theme_id INT NOT NULL, INDEX IDX_REPERTOIRE_ITEM_THEME_ITEM (repertoire_item_id), INDEX IDX_REPERTOIRE_ITEM_THEME_THEME (theme_id), PRIMARY KEY(repertoire_item_id, theme_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media_resource_theme (media_resource_id INT NOT NULL, theme_id INT NOT NULL, INDEX IDX_MEDIA_RESOURCE_THEME_RESOURCE (media_resource_id), INDEX IDX_MEDIA_RESOURCE_THEME_THEME (theme_id), PRIMARY KEY(media_resource_id, theme_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE repertoire_item_theme ADD CONSTRAINT FK_REPERTOIRE_ITEM_THEME_ITEM FOREIGN KEY (repertoire_item_id) REFERENCES repertoire_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repertoire_item_theme ADD CONSTRAINT FK_REPERTOIRE_ITEM_THEME_THEME FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_resource_theme ADD CONSTRAINT FK_MEDIA_RESOURCE_THEME_RESOURCE FOREIGN KEY (media_resource_id) REFERENCES media_resource (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_resource_theme ADD CONSTRAINT FK_MEDIA_RESOURCE_THEME_THEME FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE');
        $this->addSql("INSERT INTO theme (uuid, created_at, updated_at, label, color) VALUES
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30201', '-', '')), NOW(), NOW(), 'eau', '#0284c7'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30202', '-', '')), NOW(), NOW(), 'automne', '#c2410c'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30203', '-', '')), NOW(), NOW(), 'hiver', '#38bdf8'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30204', '-', '')), NOW(), NOW(), 'été', '#f59e0b'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30205', '-', '')), NOW(), NOW(), 'printemps', '#65a30d'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30206', '-', '')), NOW(), NOW(), 'espace', '#4338ca'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30207', '-', '')), NOW(), NOW(), 'asie', '#dc2626'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30208', '-', '')), NOW(), NOW(), 'afrique', '#92400e'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30209', '-', '')), NOW(), NOW(), 'oiseaux', '#0891b2'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30210', '-', '')), NOW(), NOW(), 'cuisine', '#e11d48'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30211', '-', '')), NOW(), NOW(), 'forêt', '#166534'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30212', '-', '')), NOW(), NOW(), 'couleurs', '#a21caf'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30213', '-', '')), NOW(), NOW(), 'amérique du sud', '#ea580c'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30214', '-', '')), NOW(), NOW(), 'plage', '#0f766e'),
            (UNHEX(REPLACE('a4db8b60-4a0c-4c65-91c3-54d8e7f30215', '-', '')), NOW(), NOW(), 'vent', '#64748b')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repertoire_item_theme DROP FOREIGN KEY FK_REPERTOIRE_ITEM_THEME_ITEM, DROP FOREIGN KEY FK_REPERTOIRE_ITEM_THEME_THEME');
        $this->addSql('ALTER TABLE media_resource_theme DROP FOREIGN KEY FK_MEDIA_RESOURCE_THEME_RESOURCE, DROP FOREIGN KEY FK_MEDIA_RESOURCE_THEME_THEME');
        $this->addSql('DROP TABLE repertoire_item_theme');
        $this->addSql('DROP TABLE media_resource_theme');
        $this->addSql('DROP TABLE theme');
        $this->addSql('ALTER TABLE instrument_tag DROP color');
    }
}
