<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260630213000 extends AbstractMigration
{
    /**
     * @var list<array{address:string, label:string, street:string}>
     */
    private const TEST_EMAIL_SEEDS = [
        [
            'address' => 'test-cornimont-01@jardinsonore-mailing-test.invalid',
            'label' => 'Cornimont test 01',
            'street' => '1 rue de la Gare',
        ],
        [
            'address' => 'test-cornimont-02@jardinsonore-mailing-test.invalid',
            'label' => 'Cornimont test 02',
            'street' => '2 rue de la Gare',
        ],
        [
            'address' => 'test-cornimont-03@jardinsonore-mailing-test.invalid',
            'label' => 'Cornimont test 03',
            'street' => '3 rue de la Gare',
        ],
        [
            'address' => 'test-cornimont-04@jardinsonore-mailing-test.invalid',
            'label' => 'Cornimont test 04',
            'street' => '4 rue de la Gare',
        ],
    ];

    public function getDescription(): string
    {
        return 'Add test organization type and seed four Cornimont newsletter recipients for mailing validation.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !str_contains($this->connection->getDatabasePlatform()::class, 'MySQL'),
            'This migration only supports MySQL.',
        );

        $existingSeedCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM email_contact WHERE email_address IN (:emails)',
            [
                'emails' => array_column(self::TEST_EMAIL_SEEDS, 'address'),
            ],
            [
                'emails' => ArrayParameterType::STRING,
            ],
        );

        if (count(self::TEST_EMAIL_SEEDS) === $existingSeedCount) {
            return;
        }

        $municipality = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, postal_code
                FROM municipality
                WHERE name = :name
                AND postal_code = :postalCode
                LIMIT 1
            SQL,
            [
                'name' => 'Cornimont',
                'postalCode' => '88310',
            ],
        );

        $this->abortIf(false === $municipality, 'Cornimont (88310) municipality is required before seeding mailing test recipients.');

        foreach (self::TEST_EMAIL_SEEDS as $seedIndex => $seed) {
            $alreadyExists = (bool) $this->connection->fetchOne(
                'SELECT 1 FROM email_contact WHERE email_address = :emailAddress LIMIT 1',
                [
                    'emailAddress' => $seed['address'],
                ],
            );

            if ($alreadyExists) {
                continue;
            }

            $this->connection->insert('directory_entry', [
                'uuid' => Uuid::v7()->toBinary(),
                'discriminator' => 'organization',
                'entry_type' => 'organization',
                'customer_status' => 'prospect',
                'active' => true,
            ], [
                'uuid' => 'binary',
            ]);
            $directoryEntryId = (int) $this->connection->lastInsertId();

            $this->connection->insert('organization', [
                'id' => $directoryEntryId,
                'name' => sprintf('Test mailing Cornimont %02d', $seedIndex + 1),
                'type' => 'test',
                'sector' => 'public',
            ]);

            $this->connection->insert('contact_details', [
                'uuid' => Uuid::v7()->toBinary(),
                'directory_entry_id' => $directoryEntryId,
            ], [
                'uuid' => 'binary',
            ]);
            $contactDetailsId = (int) $this->connection->lastInsertId();

            $this->connection->insert('address_contact', [
                'uuid' => Uuid::v7()->toBinary(),
                'contact_details_id' => $contactDetailsId,
                'municipality_id' => (int) $municipality['id'],
                'type' => 'main',
                'label' => 'Adresse de test mailing',
                'address' => $seed['street'],
                'postal_code' => (string) $municipality['postal_code'],
                'city' => 'Cornimont',
                'active' => true,
            ], [
                'uuid' => 'binary',
            ]);

            $this->connection->insert('email_contact', [
                'uuid' => Uuid::v7()->toBinary(),
                'contact_details_id' => $contactDetailsId,
                'email_address' => $seed['address'],
                'label' => $seed['label'],
                'type' => 'main',
                'opt_in_newsletter' => true,
                'active' => true,
                'source' => 'manual',
                'unsubscribe_token' => bin2hex(random_bytes(32)),
                'unsubscribed_at' => null,
            ], [
                'uuid' => 'binary',
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $directoryEntryIds = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT contact.directory_entry_id
                FROM email_contact email
                INNER JOIN contact_details contact ON contact.id = email.contact_details_id
                WHERE email.email_address IN (:emails)
            SQL,
            [
                'emails' => array_column(self::TEST_EMAIL_SEEDS, 'address'),
            ],
            [
                'emails' => ArrayParameterType::STRING,
            ],
        );

        if ([] === $directoryEntryIds) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM directory_entry WHERE id IN (:ids)',
            [
                'ids' => array_map('intval', $directoryEntryIds),
            ],
            [
                'ids' => ArrayParameterType::INTEGER,
            ],
        );
    }
}
