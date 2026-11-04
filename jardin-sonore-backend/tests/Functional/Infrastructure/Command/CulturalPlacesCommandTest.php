<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceWorkFile;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use App\Tests\Support\GeographyFixture;
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

    public function testPreviouslyChosenLineCanBeReviewedAgainWithoutChangingOtherChoices(): void
    {
        self::bootKernel(['environment' => 'test']);
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $file = new CulturalPlaceWorkFile();
        $file->save($path, ['department' => '42', 'rows' => [
            ['item' => ['id' => 'other', 'type' => 'mediatheque', 'name' => 'Autre lieu'], 'decision' => 'skip'],
            ['item' => ['id' => 'test-reconsider', 'type' => 'librairie_jeunesse', 'name' => 'Librairie fictive xyzzy'], 'decision' => 'new', 'candidatesHash' => hash('sha256', '[]')],
        ]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-review'));
            $tester->setInputs(['i']);
            self::assertSame(0, $tester->execute(['file' => $path, '--reconsider' => 2]));
            self::assertStringContainsString('2/2 — Librairie fictive xyzzy', $tester->getDisplay());
            $rows = $file->load($path)['rows'];
            self::assertSame('skip', $rows[0]['decision']);
            self::assertSame('skip', $rows[1]['decision']);
        } finally {
            unlink($path);
        }
    }

    public function testCannotReconsiderAnAppliedLine(): void
    {
        self::bootKernel(['environment' => 'test']);
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $file = new CulturalPlaceWorkFile();
        $file->save($path, ['department' => '42', 'rows' => [[
            'item' => ['id' => 'already-applied', 'type' => 'mediatheque', 'name' => 'Lieu'],
            'decision' => 'linked', 'organizationId' => 42, 'applied' => true,
        ]]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-review'));
            self::assertSame(1, $tester->execute(['file' => $path, '--reconsider' => 1]));
            self::assertSame(42, $file->load($path)['rows'][0]['organizationId']);
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

    public function testApplyChecksEveryNewMatchBeforeWritingAnyPendingRow(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $suffix = bin2hex(random_bytes(8));
        $staleSourceId = "stale-{$suffix}";
        $unrelatedSourceId = "unrelated-{$suffix}";
        $municipality = GeographyFixture::municipality($entityManager, "Médiathèque Test {$suffix}", '42155')->setInseeCode('98996');
        $existing = (new OrganizationEntity())->setName("Médiathèque déjà importée {$suffix}")->setType(OrganizationType::MEDIATHEQUE);
        $existing->getContactDetails()->addAddressContact((new AddressContactEntity())->setMunicipality($municipality));
        $entityManager->persist($existing);
        $entityManager->flush();
        $workFile = new CulturalPlaceWorkFile();
        $workFile->save($path, ['department' => '42', 'rows' => [
            [
                'item' => ['id' => $unrelatedSourceId, 'type' => 'mediatheque', 'name' => "Lieu inédit {$suffix}"],
                'decision' => 'new', 'candidatesHash' => hash('sha256', '[]'),
            ],
            [
                'item' => ['id' => $staleSourceId, 'type' => 'mediatheque', 'name' => "Médiathèque déjà importée {$suffix}", 'commune' => "Médiathèque Test {$suffix}", 'codeCommune' => '98996'],
                'decision' => 'new', 'candidatesHash' => hash('sha256', '[]'),
            ],
        ]]);

        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-apply'));
            try {
                $tester->execute(['file' => $path], ['interactive' => false]);
                self::fail('Un changement doit être signalé avant toute écriture.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Aucune nouvelle fiche n’a été écrite', $exception->getMessage());
            }
            $rows = $workFile->load($path)['rows'];
            self::assertArrayNotHasKey('applied', $rows[0]);
            self::assertNull($rows[1]['decision']);
            self::assertCount(0, $entityManager->getRepository(DirectoryImportLinkEntity::class)->findBy(['source' => 'culture-lieux', 'externalId' => $unrelatedSourceId]));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
            unlink($path);
        }
    }

    public function testApplyLinksMunicipalityByInseeCodeDespiteAbbreviatedName(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $sourceId = bin2hex(random_bytes(10));
        $municipality = GeographyFixture::municipality($entityManager, "Saint-Just {$sourceId}", '42170')->setInseeCode('98999');
        $entityManager->flush();
        $workFile = new CulturalPlaceWorkFile();
        $workFile->save($path, ['department' => '42', 'source' => 'manual-teaching-test', 'rows' => [[
            'item' => ['id' => $sourceId, 'type' => 'mediatheque', 'name' => "Médiathèque {$sourceId}", 'address' => '1 place Test 42170', 'commune' => 'ST JUST TEST', 'codeCommune' => '98999'],
            'decision' => 'new',
        ]]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-apply'));
            self::assertSame(0, $tester->execute(['file' => $path], ['interactive' => false]));
            $id = $workFile->load($path)['rows'][0]['organizationId'];
            self::assertCount(1, $entityManager->getRepository(DirectoryImportLinkEntity::class)->findBy(['source' => 'manual-teaching-test', 'externalId' => $sourceId]));
            $entityManager->clear();
            $saved = $entityManager->find(OrganizationEntity::class, $id);
            self::assertSame($municipality->getId(), $saved->getContactDetails()->getAddressContacts()->first()->getMunicipality()->getId());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
            unlink($path);
        }
    }

    public function testApplyRefusesAnOldBatchWithoutMunicipalityCode(): void
    {
        self::bootKernel(['environment' => 'test']);
        $path = tempnam(sys_get_temp_dir(), 'cultural-');
        self::assertIsString($path);
        $workFile = new CulturalPlaceWorkFile();
        $workFile->save($path, ['department' => '42', 'rows' => [[
            'item' => ['id' => 'old-source', 'type' => 'mediatheque', 'name' => 'Ancien lot', 'commune' => 'ST JUST ST RAMBERT'],
            'decision' => 'new',
        ]]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-apply'));
            self::assertSame(1, $tester->execute(['file' => $path], ['interactive' => false]));
            self::assertStringContainsString('Code INSEE absent', $tester->getDisplay());
            self::assertArrayNotHasKey('applied', $workFile->load($path)['rows'][0]);
        } finally {
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
        $sharedEmailLink = (new EmailContactLinkEntity())->setEmailAddress("{$suffix}@example.test");
        $organization->getContactDetails()->addEmailContactLink($sharedEmailLink);
        $entityManager->persist($organization);
        $otherOrganization = (new OrganizationEntity())->setName("Librairie Test {$suffix}")->setType(OrganizationType::CRECHE);
        $otherOrganization->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailContact($sharedEmailLink->getEmailContact()));
        $entityManager->persist($otherOrganization);
        $entityManager->flush();
        $workFile = new CulturalPlaceWorkFile();
        $workFile->save($path, ['department' => '42', 'rows' => [[
            'item' => ['id' => $suffix, 'type' => 'librairie_jeunesse', 'name' => "Librairie Test {$suffix}", 'mail' => "{$suffix}@example.test"],
            'decision' => null,
        ]]]);
        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-review'));
            $tester->setInputs(['1']);
            self::assertSame(0, $tester->execute(['file' => $path]));
            self::assertStringContainsString('À importer', $tester->getDisplay());
            self::assertStringContainsString('Fiche #' . $organization->getId(), $tester->getDisplay());
            self::assertMatchesRegularExpression('/À importer[^\n]*\[1\] Fiche #' . $organization->getId() . '[^\n]*\[2\] Fiche #' . $otherOrganization->getId() . '/u', $tester->getDisplay());
            self::assertStringContainsString('i = ignorer', $tester->getDisplay());
            $savedRow = $workFile->load($path)['rows'][0];
            self::assertSame('linked', $savedRow['decision']);
            self::assertSame($organization->getId(), $savedRow['organizationId']);
            self::assertArrayHasKey('candidateHash', $savedRow);
            $tester->setInputs(['2']);
            self::assertSame(0, $tester->execute(['file' => $path, '--reconsider' => 1]));
            self::assertSame($otherOrganization->getId(), $workFile->load($path)['rows'][0]['organizationId']);
            $batch = $workFile->load($path);
            $batch['rows'][0]['decision'] = null;
            $workFile->save($path, $batch);
            $tester->setInputs(['x', 'i']);
            self::assertSame(0, $tester->execute(['file' => $path]));
            self::assertStringContainsString('Choisis un numéro affiché, 0 ou i.', $tester->getDisplay());
            self::assertSame('skip', $workFile->load($path)['rows'][0]['decision']);
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
                self::assertStringContainsString('Reprenez la revue', $exception->getMessage());
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
