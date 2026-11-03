<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceWorkFile;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CulturalPlacesCommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testReviewRecordsCreationAndCanBeResumed(): void
    {
        self::bootKernel(['environment' => 'test']);
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $file = new CulturalPlaceWorkFile();
        $file->save($path, ['department' => '42', 'rows' => [[
            'item' => ['id' => 'test-id', 'type' => 'librairie_jeunesse', 'name' => 'Une librairie uniquement fictive xyzzy'],
            'youth' => true, 'sourceUpdatedAt' => null, 'decision' => null,
        ]]]);
        try {
            $commandTester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-review'));
            self::assertSame(0, $commandTester->execute(['file' => $path], ['interactive' => false]));
            self::assertSame('new', $file->load($path)['rows'][0]['decision']);
            self::assertSame(0, $commandTester->execute(['file' => $path], ['interactive' => false]));
        } finally {
            unlink($path);
        }
    }

    public function testApplyCanResumeWithoutCreatingDuplicates(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $sourceId = bin2hex(random_bytes(10));
        $workFile = new CulturalPlaceWorkFile();
        $workFile->save($path, ['department' => '42', 'rows' => [[
            'item' => ['id' => $sourceId, 'type' => 'librairie_jeunesse', 'name' => "Librairie fictive {$sourceId}"],
            'decision' => 'new', 'youth' => true, 'sourceUpdatedAt' => null,
        ]]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-apply'));
            self::assertSame(0, $tester->execute(['file' => $path], ['interactive' => false]));
            self::assertSame(0, $tester->execute(['file' => $path], ['interactive' => false]));
            $savedRow = $workFile->load($path)['rows'][0];
            self::assertSame('created', $savedRow['result']);
            self::assertIsInt($savedRow['organizationId']);
            self::assertStringContainsString((string) $savedRow['organizationId'], $tester->getDisplay());
            self::assertCount(1, $entityManager->getRepository(DirectoryImportLinkEntity::class)->findBy(['source' => 'culture-lieux', 'externalId' => $sourceId]));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
            unlink($path);
        }
    }

    public function testExistingDetailsAndDisabledEmailArePreserved(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $sourceId = bin2hex(random_bytes(10));
        $email = "old-{$sourceId}@example.test";
        $sharedEmail = "shared-old-{$sourceId}@example.test";
        $disabledEmail = (new EmailContactEntity())->setEmailAddress($sharedEmail)->setActive(false)->setOptInNewsletter(false);
        $entityManager->persist($disabledEmail);
        $organization = (new OrganizationEntity())->setName('Nom conservé')->setWebsiteUrl('https://conserve.example.test');
        $emailLink = (new EmailContactLinkEntity())->setEmailAddress($email)->setActive(false)->setOptInNewsletter(false);
        $organization->getContactDetails()->addEmailContactLink($emailLink);
        $entityManager->persist($organization);
        $link = (new DirectoryImportLinkEntity())->setDirectoryEntry($organization)->setSource('culture-lieux')->setExternalId($sourceId)->setPayloadHash(str_repeat('a', 64));
        $entityManager->persist($link);
        $entityManager->flush();
        $workFile = new CulturalPlaceWorkFile();
        $workFile->save($path, ['department' => '42', 'rows' => [[
            'item' => ['id' => $sourceId, 'type' => 'mediatheque', 'name' => 'Nom extérieur', 'siteWeb' => 'https://different.example.test', 'mail' => $email, 'additionalEmails' => [$sharedEmail]],
            'decision' => 'linked', 'organizationId' => $organization->getId(), 'youth' => false,
        ]]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-apply'));
            self::assertSame(0, $tester->execute(['file' => $path], ['interactive' => false]));
            $entityManager->clear();
            $saved = $entityManager->find(OrganizationEntity::class, $organization->getId());
            self::assertSame('Nom conservé', $saved->getName());
            self::assertSame('https://conserve.example.test', $saved->getWebsiteUrl());
            $savedLink = $saved->getContactDetails()->getEmailContactLinks()->first();
            self::assertFalse($savedLink->isActive());
            self::assertFalse($savedLink->hasOptInNewsletter());
            self::assertCount(1, $saved->getContactDetails()->getEmailContactLinks());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
            unlink($path);
        }
    }

    public function testReviewDisplaysAndRemembersChosenMatch(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $suffix = bin2hex(random_bytes(5));
        $organization = (new OrganizationEntity())->setName("Librairie Test {$suffix}")->setType(OrganizationType::LIBRAIRIE_JEUNESSE);
        $organization->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress("{$suffix}@example.test"));
        $entityManager->persist($organization);
        $entityManager->flush();
        $workFile = new CulturalPlaceWorkFile();
        $workFile->save($path, ['department' => '42', 'rows' => [[
            'item' => ['id' => $suffix, 'type' => 'librairie_jeunesse', 'name' => "Librairie Test {$suffix}", 'mail' => "{$suffix}@example.test"],
            'decision' => null,
        ]]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-review'));
            $tester->setInputs(['2']);
            self::assertSame(0, $tester->execute(['file' => $path]));
            self::assertStringContainsString('À importer', $tester->getDisplay());
            self::assertStringContainsString('Fiche #' . $organization->getId(), $tester->getDisplay());
            $savedRow = $workFile->load($path)['rows'][0];
            self::assertSame('linked', $savedRow['decision']);
            self::assertSame($organization->getId(), $savedRow['organizationId']);
            self::assertArrayHasKey('candidateHash', $savedRow);
            $batch = $workFile->load($path);
            $batch['rows'][0]['decision'] = null;
            $workFile->save($path, $batch);
            $tester->setInputs(['0']);
            self::assertSame(0, $tester->execute(['file' => $path]));
            self::assertSame('new', $workFile->load($path)['rows'][0]['decision']);
            $apply = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-apply'));
            $organization->setWebsiteUrl('https://changed.example.test');
            $entityManager->flush();
            try {
                $apply->execute(['file' => $path], ['interactive' => false]);
                self::fail('Un changement après revue doit interrompre l’import.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('reprendre la revue', $exception->getMessage());
            }
            self::assertNull($workFile->load($path)['rows'][0]['decision']);
            $tester->setInputs(['0']);
            self::assertSame(0, $tester->execute(['file' => $path]));
            self::assertSame(0, $apply->execute(['file' => $path], ['interactive' => false]));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
            unlink($path);
        }
    }
}
