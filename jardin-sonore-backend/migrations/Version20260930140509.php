<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930140509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track first session availability per organization and preserve triggering organizations for notification deliveries without retroactive mail.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE session_organization_availability (id INT AUTO_INCREMENT NOT NULL, first_available_at DATETIME NOT NULL, session_summary_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_1B187076CA54DF07 (session_summary_id), INDEX IDX_1B18707632C8A3DE (organization_id), UNIQUE INDEX uniq_session_organization_availability (session_summary_id, organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE session_organization_availability ADD CONSTRAINT FK_1B187076CA54DF07 FOREIGN KEY (session_summary_id) REFERENCES session_summary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_organization_availability ADD CONSTRAINT FK_1B18707632C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_notification_delivery ADD organization_ids JSON DEFAULT NULL');
        $this->addSql('INSERT INTO session_organization_availability (session_summary_id, organization_id, first_available_at) SELECT share.session_summary_id, share.organization_id, GREATEST(COALESCE(summary.first_published_at, summary.updated_at), share.shared_at) FROM session_summary_organization share INNER JOIN session_summary summary ON summary.id = share.session_summary_id WHERE summary.first_published_at IS NOT NULL OR summary.published = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_organization_availability DROP FOREIGN KEY FK_1B187076CA54DF07');
        $this->addSql('ALTER TABLE session_organization_availability DROP FOREIGN KEY FK_1B18707632C8A3DE');
        $this->addSql('DROP TABLE session_organization_availability');
        $this->addSql('ALTER TABLE session_notification_delivery DROP organization_ids');
    }
}
