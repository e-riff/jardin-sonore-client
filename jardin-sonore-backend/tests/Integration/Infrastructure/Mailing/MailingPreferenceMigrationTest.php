<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineMigrations\Version20261127083218;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MailingPreferenceMigrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testBackfillPreservesEveryHistoricalBlockWithoutInventingSubscription(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $connection->getDatabase());
        $connection->beginTransaction();
        try {
            $cases = [];
            foreach ([false, true] as $optIn) {
                foreach ([false, true] as $dated) {
                    foreach ([false, true] as $active) {
                        $contact = (new EmailContactEntity())->setEmailAddress('migration-' . bin2hex(random_bytes(6)) . '@example.test')
                            ->setOptInNewsletter($optIn)->setActive($active)->setUnsubscribedAt($dated ? new DateTimeImmutable('2026-01-01') : null);
                        $entityManager->persist($contact);
                        $cases[] = [$contact, !$optIn || $dated, $active, $dated];
                    }
                }
            }
            $entityManager->flush();
            require_once __DIR__ . '/../../../../migrations/Version20261127083218.php';
            $migration = new Version20261127083218($connection, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                if (str_starts_with($query->getStatement(), 'UPDATE email_contact')) {
                    $connection->executeStatement($query->getStatement());
                }
            }
            foreach ($cases as [$contact, $blocked, $active, $dated]) {
                $entityManager->refresh($contact);
                self::assertSame(!$blocked, $contact->isDirectoryMailingAllowed());
                self::assertSame($blocked, $contact->isNewsletterRefused());
                self::assertSame($blocked, $contact->isOutreachRefused());
                self::assertSame($active, $contact->isActive());
                self::assertSame($dated, null !== $contact->getNewsletterRefusedAt());
                self::assertFalse($contact->hasFreeNewsletterSubscription());
            }
        } finally {
            $connection->rollBack();
        }
    }
}
