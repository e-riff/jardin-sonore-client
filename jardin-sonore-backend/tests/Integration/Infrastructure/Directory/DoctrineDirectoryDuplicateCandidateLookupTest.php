<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Directory;

use App\Infrastructure\Directory\DoctrineDirectoryDuplicateCandidateLookup;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoctrineDirectoryDuplicateCandidateLookupTest extends TestCase
{
    #[Test]
    public function itReturnsRepeatedNamesAsReviewCandidatesWithoutMergingThem(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE organization (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL)');
        $connection->executeStatement('CREATE TABLE contact_details (id INTEGER PRIMARY KEY, directory_entry_id INTEGER NOT NULL)');
        $connection->executeStatement('CREATE TABLE address_contact (id INTEGER PRIMARY KEY, contact_details_id INTEGER NOT NULL, address TEXT, postal_code VARCHAR(5), city VARCHAR(255), active BOOLEAN NOT NULL)');
        $connection->executeStatement('CREATE TABLE contact_details_email_link (contact_details_id INTEGER NOT NULL, email_contact_id INTEGER NOT NULL)');
        $connection->executeStatement('CREATE TABLE email_contact (id INTEGER PRIMARY KEY, email_address VARCHAR(255) NOT NULL)');
        $connection->executeStatement('CREATE TABLE contact_details_phone_link (contact_details_id INTEGER NOT NULL, phone_contact_id INTEGER NOT NULL)');
        $connection->executeStatement('CREATE TABLE phone_contact (id INTEGER PRIMARY KEY, phone_number VARCHAR(64) NOT NULL)');

        $connection->insert('organization', ['id' => 10, 'name' => 'Crèche Les Griottes']);
        $connection->insert('organization', ['id' => 20, 'name' => 'CRECHE LES GRIOTTES']);
        $connection->insert('organization', ['id' => 30, 'name' => 'Les Ecureuils']);
        $connection->insert('contact_details', ['id' => 1, 'directory_entry_id' => 10]);
        $connection->insert('contact_details', ['id' => 2, 'directory_entry_id' => 20]);
        $connection->insert('address_contact', ['id' => 1, 'contact_details_id' => 1, 'address' => '15 rue du Centre', 'postal_code' => '69001', 'city' => 'Lyon', 'active' => 1]);
        $connection->insert('address_contact', ['id' => 2, 'contact_details_id' => 2, 'address' => '15 RUE DU CENTRE', 'postal_code' => '69001', 'city' => 'LYON', 'active' => 1]);
        $connection->insert('organization', ['id' => 40, 'name' => 'Alpha']);
        $connection->insert('organization', ['id' => 50, 'name' => 'Beta']);
        $connection->insert('contact_details', ['id' => 3, 'directory_entry_id' => 40]);
        $connection->insert('contact_details', ['id' => 4, 'directory_entry_id' => 50]);
        $connection->insert('email_contact', ['id' => 1, 'email_address' => 'contact@example.org']);
        $connection->insert('contact_details_email_link', ['contact_details_id' => 3, 'email_contact_id' => 1]);
        $connection->insert('contact_details_email_link', ['contact_details_id' => 4, 'email_contact_id' => 1]);
        $connection->insert('phone_contact', ['id' => 1, 'phone_number' => '+33412345678']);
        $connection->insert('contact_details_phone_link', ['contact_details_id' => 3, 'phone_contact_id' => 1]);
        $connection->insert('contact_details_phone_link', ['contact_details_id' => 4, 'phone_contact_id' => 1]);
        $connection->insert('contact_details', ['id' => 5, 'directory_entry_id' => 60]);
        $connection->insert('contact_details_email_link', ['contact_details_id' => 5, 'email_contact_id' => 1]);

        $candidateLookup = new DoctrineDirectoryDuplicateCandidateLookup($connection);
        $candidates = $candidateLookup->findCandidates(50);

        self::assertCount(2, $candidates);
        self::assertSame(10, $candidates[0]->firstOrganizationId);
        self::assertSame(20, $candidates[0]->secondOrganizationId);
        self::assertContains('same_name', $candidates[0]->signals);
        self::assertContains('same_address', $candidates[0]->signals);
        self::assertStringContainsString('15 rue du Centre', $candidates[0]->firstLocation);
        self::assertSame([40, 50], [$candidates[1]->firstOrganizationId, $candidates[1]->secondOrganizationId]);
        self::assertContains('same_email', $candidates[1]->signals);
        self::assertContains('same_phone', $candidates[1]->signals);
    }
}
