<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove private notes from every stored session sequence.';
    }

    public function up(Schema $schema): void
    {
        $sessionSummaries = $this->connection->iterateAssociative('SELECT uuid, sequences FROM session_summary');

        foreach ($sessionSummaries as $sessionSummary) {
            $sequences = json_decode((string) $sessionSummary['sequences'], true);
            if (!is_array($sequences)) {
                continue;
            }

            $hasRemovedNotes = false;
            foreach ($sequences as &$sequence) {
                if (!is_array($sequence) || !array_key_exists('notes', $sequence)) {
                    continue;
                }

                unset($sequence['notes']);
                $hasRemovedNotes = true;
            }
            unset($sequence);

            if ($hasRemovedNotes) {
                $this->connection->update(
                    'session_summary',
                    ['sequences' => json_encode($sequences, JSON_THROW_ON_ERROR)],
                    ['uuid' => $sessionSummary['uuid']],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Deleted sequence notes cannot be restored.
    }
}
