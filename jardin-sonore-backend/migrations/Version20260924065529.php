<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class Version20260924065529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stable slugs to sessions and repertoire items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE repertoire_item ADD slug VARCHAR(255) DEFAULT NULL');
        $this->fillSlugs('repertoire_item');
        $this->addSql('CREATE UNIQUE INDEX uniq_repertoire_item_slug ON repertoire_item (slug)');
        $this->addSql('ALTER TABLE repertoire_item MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE session_summary ADD slug VARCHAR(255) DEFAULT NULL');
        $this->fillSlugs('session_summary');
        $this->addSql('CREATE UNIQUE INDEX uniq_session_summary_slug ON session_summary (slug)');
        $this->addSql('ALTER TABLE session_summary MODIFY slug VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_repertoire_item_slug ON repertoire_item');
        $this->addSql('ALTER TABLE repertoire_item DROP slug');
        $this->addSql('DROP INDEX uniq_session_summary_slug ON session_summary');
        $this->addSql('ALTER TABLE session_summary DROP slug');
    }

    private function fillSlugs(string $table): void
    {
        $slugger = new AsciiSlugger();
        $takenSlugs = [];

        foreach ($this->connection->fetchAllAssociative("SELECT id, title FROM {$table} ORDER BY title ASC, id ASC") as $row) {
            $baseSlug = $slugger->slug((string) $row['title'])->lower()->toString();
            $baseSlug = '' === $baseSlug ? 'contenu' : $baseSlug;
            $slug = $baseSlug;
            $suffix = 2;

            while (isset($takenSlugs[$slug])) {
                $slug = "{$baseSlug}-{$suffix}";
                ++$suffix;
            }

            $takenSlugs[$slug] = true;
            $this->addSql("UPDATE {$table} SET slug = :slug WHERE id = :id", ['slug' => $slug, 'id' => (int) $row['id']]);
        }
    }
}
