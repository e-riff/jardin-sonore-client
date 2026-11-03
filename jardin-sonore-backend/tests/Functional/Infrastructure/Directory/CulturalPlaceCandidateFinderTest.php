<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Directory;

use App\Application\Directory\CulturalPlaceWorkFile;
use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Directory\CulturalPlaceCandidateFinder;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use App\Tests\Support\GeographyFixture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CulturalPlaceCandidateFinderTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testFindsAbbreviatedNameInSameMunicipalityByInseeCode(): void
    {
        self::bootKernel(['environment' => 'test']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $municipality = GeographyFixture::municipality($em, 'Saint-Haon-le-Vieux Test', '42155')->setInseeCode('98998');
            $organization = (new OrganizationEntity())->setName('Bibliothèque de Saint-Haon-le-Vieux')->setType(OrganizationType::MEDIATHEQUE);
            $organization->getContactDetails()->addAddressContact((new AddressContactEntity())->setMunicipality($municipality));
            $em->persist($organization);
            $em->flush();
            $item = DirectoryEstablishmentImportItem::fromArray(['id' => 'test', 'type' => 'mediatheque', 'name' => 'Bibliothèque - Saint-Haon-Le-Vieux', 'commune' => 'ST HAON LE VIEUX', 'codeCommune' => '98998']);
            $matches = self::getContainer()->get(CulturalPlaceCandidateFinder::class)->findCandidates($item);
            self::assertContains($organization->getId(), array_map(static fn ($match): int => $match->organizationId, $matches));

            $path = tempnam(sys_get_temp_dir(), 'cultural-review-');
            self::assertIsString($path);
            try {
                $file = new CulturalPlaceWorkFile();
                $file->save($path, ['department' => '42', 'rows' => [[
                    'item' => $item->rawPayload, 'decision' => 'new', 'candidatesHash' => hash('sha256', '[]'),
                ]]]);
                $tester = new CommandTester((new Application(self::$kernel))->find('app:directory:cultural-places-review'));
                self::assertSame(0, $tester->execute(['file' => $path], ['interactive' => false]));
                self::assertNull($file->load($path)['rows'][0]['decision']);
            } finally {
                unlink($path);
            }
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }

    public function testFindsStrongNameDespiteIncorrectMunicipalityOnExistingEntry(): void
    {
        self::bootKernel(['environment' => 'test']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $municipality = GeographyFixture::municipality($em, 'Montbrison Test', '42600')->setInseeCode('98997');
            $organization = (new OrganizationEntity())->setName('Médiathèque Loire Forez Saint-Just-Saint-Rambert')->setType(OrganizationType::MEDIATHEQUE);
            $organization->getContactDetails()->addAddressContact((new AddressContactEntity())->setMunicipality($municipality));
            $em->persist($organization);
            $em->flush();
            $item = DirectoryEstablishmentImportItem::fromArray(['id' => 'test', 'type' => 'mediatheque', 'name' => 'Médiathèque - Saint-Just-Saint-Rambert', 'commune' => 'ST JUST ST RAMBERT', 'codeCommune' => '42279']);
            $matches = self::getContainer()->get(CulturalPlaceCandidateFinder::class)->findCandidates($item);
            self::assertContains($organization->getId(), array_map(static fn ($match): int => $match->organizationId, $matches));
        } finally {
            $connection->rollBack();
            $em->clear();
        }
    }
}
