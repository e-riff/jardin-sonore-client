<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Directory;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Infrastructure\Directory\DirectoryEstablishmentUpserter;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DirectoryEstablishmentUpserterTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[DataProvider('organizationNames')]
    public function testImportedNamesReceiveTheirSpecificType(string $name, ?string $expectedType): void
    {
        self::bootKernel(['environment' => 'test']);
        $upserter = self::getContainer()->get(DirectoryEstablishmentUpserter::class);
        $organization = new OrganizationEntity();
        $item = DirectoryEstablishmentImportItem::fromArray(['id' => 'test-1', 'type' => 'OTHER', 'name' => $name]);

        $upserter->hydrateOrganization($organization, $item);

        self::assertSame($expectedType, $organization->getType()?->value);
    }

    public static function organizationNames(): iterable
    {
        yield 'MJC' => ['MJC des Tilleuls', 'mjc'];
        yield 'maison pour tous' => ['Maison pour tous de Bellevue', 'mjc'];
        yield 'centre de loisirs' => ['Centre de loisirs des Écureuils', 'centre'];
        yield 'ALSH' => ['ALSH Les Petits Loups', 'centre'];
        yield 'accueil de loisirs' => ['Accueil de loisirs du Parc', 'centre'];
        yield 'centre social' => ['Centre social du quartier', 'centre'];
        yield 'ludothèque by type' => ['Ludothèque du Pilat', 'ludotheque'];
        yield 'ludothèque accent-insensitive' => ['Ludotheque Les Petits Jeux', 'ludotheque'];
        yield 'centre social with a ludothèque' => ['Ludothèque 1,2,3 Soleil – Centre social Les 2 Rivières', 'ludotheque'];
        yield 'LAEP' => ['LAEP La Parenthèse', 'lieu_parentalite'];
        yield 'café poussette' => ['Café poussette Les Petits Pas', 'cafe_parents'];
        yield 'café des enfants' => ['Café des enfants du Bourg', 'cafe_parents'];
        yield 'lieu de parentalité' => ['Lieu de parentalité du Parc', 'lieu_parentalite'];
        yield 'centre indéterminé' => ['Centre des Tilleuls', null];
    }

    public function testMptImportTypeIsClassifiedAsMjc(): void
    {
        self::bootKernel(['environment' => 'test']);
        $upserter = self::getContainer()->get(DirectoryEstablishmentUpserter::class);
        $organization = new OrganizationEntity();
        $item = DirectoryEstablishmentImportItem::fromArray(['id' => 'mpt-biviers', 'type' => 'MPT', 'name' => 'MPT Biviers']);

        $upserter->hydrateOrganization($organization, $item);

        self::assertSame('mjc', $organization->getType()?->value);
    }

    public function testLaepImportTypeIsClassifiedAsParentalityPlace(): void
    {
        self::bootKernel(['environment' => 'test']);
        $upserter = self::getContainer()->get(DirectoryEstablishmentUpserter::class);
        $organization = new OrganizationEntity();
        $item = DirectoryEstablishmentImportItem::fromArray(['id' => 'laep-cocon-9', 'type' => 'LAEP', 'name' => 'Le Cocon 9']);

        $upserter->hydrateOrganization($organization, $item);

        self::assertSame('lieu_parentalite', $organization->getType()?->value);
    }

    public function testAdditionalOfficeAddressDoesNotReplacePrimaryAddress(): void
    {
        self::bootKernel(['environment' => 'test']);
        $upserter = self::getContainer()->get(DirectoryEstablishmentUpserter::class);
        $organization = new OrganizationEntity();
        $item = DirectoryEstablishmentImportItem::fromArray([
            'id' => 'mjc-parmentier',
            'type' => 'MJC',
            'name' => 'MJC Parmentier',
            'address' => '3, rue Parmentier, 38000 Grenoble',
            'commune' => 'Grenoble',
        ]);

        $upserter->upsertAddressContact($organization->getContactDetails(), $item);
        $upserter->upsertAdditionalAddressContact($organization->getContactDetails(), '17, rue Daguerre et Niépce, 38000 Grenoble', 'Grenoble');

        self::assertCount(2, $organization->getContactDetails()->getAddressContacts());
        self::assertSame('3, rue Parmentier, 38000 Grenoble', $organization->getContactDetails()->getAddressContacts()->first()->getAddress());
        self::assertSame('17, rue Daguerre et Niépce, 38000 Grenoble', $organization->getContactDetails()->getAddressContacts()->last()->getAddress());
    }
}
