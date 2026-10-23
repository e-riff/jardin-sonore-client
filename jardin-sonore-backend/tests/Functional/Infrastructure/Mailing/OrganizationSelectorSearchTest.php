<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Mailing;

use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Mailing\DoctrineNewsletterAudienceOptionsQuery;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrganizationSelectorSearchTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testPostalCodeAndCitySearchReturnOneChoiceWithTheSameLabel(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $organizationEntity = (new OrganizationEntity())->setName("École {$suffix}");
        $firstAddress = (new AddressContactEntity())->setPostalCode('42420')->setCity("Lorette {$suffix}");
        $secondAddress = (new AddressContactEntity())->setPostalCode('42700')->setCity('Firminy');
        $organizationEntity->getContactDetails()?->addAddressContact($firstAddress)->addAddressContact($secondAddress);
        $entityManager->persist($organizationEntity);
        $entityManager->persist($firstAddress);
        $entityManager->persist($secondAddress);
        $entityManager->flush();

        $query = self::getContainer()->get(DoctrineNewsletterAudienceOptionsQuery::class);
        $postalResults = $query->searchOrganizationAutocompleteChoices('42420', 1, 100)['results'];
        $cityResults = $query->searchOrganizationAutocompleteChoices("Lorette {$suffix}", 1, 100)['results'];
        $uuid = $organizationEntity->getUuid()->toRfc4122();
        $matchingPostal = array_values(array_filter($postalResults, static fn (array $result): bool => $result['value'] === $uuid));
        $matchingCity = array_values(array_filter($cityResults, static fn (array $result): bool => $result['value'] === $uuid));

        self::assertCount(1, $matchingPostal);
        self::assertCount(1, $matchingCity);
        self::assertSame("École {$suffix} — 42420 — Lorette {$suffix}", $matchingPostal[0]['text']);
        self::assertSame($matchingPostal[0]['text'], $query->getOrganizationLabelsByUuids([$uuid])[$uuid]);
    }
}
