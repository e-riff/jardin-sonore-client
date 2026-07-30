<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use JsonException;

final class Version20260730130646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds ordered session recommendation references.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary ADD recommendation_uuids JSON DEFAULT NULL');

        foreach ($this->connection->fetchAllAssociative('SELECT id, sequences FROM session_summary') as $row) {
            try {
                $sequences = json_decode((string) $row['sequences'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $sequences = [];
            }

            $recommendationUuids = [];
            $remainingSequences = [];

            foreach (is_array($sequences) ? $sequences : [] as $sequence) {
                if (!is_array($sequence) || 'session_recommendation' !== ($sequence['sourceKind'] ?? null) || !is_string($sequence['sourceUuid'] ?? null)) {
                    $remainingSequences[] = $sequence;

                    continue;
                }

                $recommendationUuids[] = $sequence['sourceUuid'];
            }

            $this->addSql(
                'UPDATE session_summary SET sequences = :sequences, recommendation_uuids = :recommendationUuids, further_exploration = NULL WHERE id = :id',
                [
                    'sequences' => json_encode($remainingSequences, JSON_THROW_ON_ERROR),
                    'recommendationUuids' => json_encode(array_values(array_unique($recommendationUuids)), JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );
        }

        $this->addSql('ALTER TABLE session_summary MODIFY recommendation_uuids JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary DROP recommendation_uuids');
    }
}
