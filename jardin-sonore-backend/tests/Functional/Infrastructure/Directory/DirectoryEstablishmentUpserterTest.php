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
        yield 'centre de loisirs' => ['Centre de loisirs des Écureuils', 'centre_loisirs'];
        yield 'ALSH' => ['ALSH Les Petits Loups', 'centre_loisirs'];
        yield 'accueil de loisirs' => ['Accueil de loisirs du Parc', 'centre_loisirs'];
        yield 'centre social' => ['Centre social du quartier', 'centre'];
        yield 'LAEP' => ['LAEP La Parenthèse', 'lieu_parentalite'];
        yield 'café poussette' => ['Café poussette Les Petits Pas', 'lieu_parentalite'];
        yield 'café des enfants' => ['Café des enfants du Bourg', 'lieu_parentalite'];
        yield 'lieu de parentalité' => ['Lieu de parentalité du Parc', 'lieu_parentalite'];
        yield 'centre indéterminé' => ['Centre des Tilleuls', null];
    }
}
