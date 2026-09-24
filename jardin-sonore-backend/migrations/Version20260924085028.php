<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924085028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds categories to sessions and links existing matching legacy subtitles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE session_summary_theme (session_summary_id INT NOT NULL, theme_id INT NOT NULL, INDEX IDX_E199E61DCA54DF07 (session_summary_id), INDEX IDX_E199E61D59027487 (theme_id), PRIMARY KEY (session_summary_id, theme_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE session_summary_theme ADD CONSTRAINT FK_E199E61DCA54DF07 FOREIGN KEY (session_summary_id) REFERENCES session_summary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_summary_theme ADD CONSTRAINT FK_E199E61D59027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE');
        $this->addSql("INSERT IGNORE INTO session_summary_theme (session_summary_id, theme_id) SELECT session_summary.id, theme.id FROM session_summary INNER JOIN theme ON TRIM(session_summary.theme) = theme.label WHERE session_summary.theme IS NOT NULL AND TRIM(session_summary.theme) <> ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary_theme DROP FOREIGN KEY FK_E199E61DCA54DF07');
        $this->addSql('ALTER TABLE session_summary_theme DROP FOREIGN KEY FK_E199E61D59027487');
        $this->addSql('DROP TABLE session_summary_theme');
    }
}
