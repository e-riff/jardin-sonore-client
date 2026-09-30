<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930074508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store one durable first-publication notification per session and portal user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE session_notification_delivery (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, queued_at DATETIME DEFAULT NULL, sent_at DATETIME DEFAULT NULL, attempts INT DEFAULT 0 NOT NULL, last_error LONGTEXT DEFAULT NULL, session_summary_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_FBB16BF7CA54DF07 (session_summary_id), INDEX IDX_FBB16BF7A76ED395 (user_id), INDEX idx_session_notification_status_queued (status, queued_at), UNIQUE INDEX uniq_session_notification_session_user (session_summary_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE session_notification_delivery ADD CONSTRAINT FK_FBB16BF7CA54DF07 FOREIGN KEY (session_summary_id) REFERENCES session_summary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_notification_delivery ADD CONSTRAINT FK_FBB16BF7A76ED395 FOREIGN KEY (user_id) REFERENCES portal_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_notification_delivery DROP FOREIGN KEY FK_FBB16BF7CA54DF07');
        $this->addSql('ALTER TABLE session_notification_delivery DROP FOREIGN KEY FK_FBB16BF7A76ED395');
        $this->addSql('DROP TABLE session_notification_delivery');
    }
}
