<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Form;

use App\Application\Form\OrganizationAutocompleteType;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class OrganizationAutocompleteTypeTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-organization-autocomplete-cache';
            }
        };
    }

    public function testItFiltersOrganizationsUsingTheTypedQuery(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('admin-' . bin2hex(random_bytes(8)) . '@autocomplete.test')->setPassword('unused');
        $firstOrganizationSearchTerm = 'Alouette ' . bin2hex(random_bytes(8));
        $secondOrganizationSearchTerm = 'Boreale ' . bin2hex(random_bytes(8));
        $firstOrganizationEntity = (new OrganizationEntity())->setName($firstOrganizationSearchTerm);
        $secondOrganizationEntity = (new OrganizationEntity())->setName($secondOrganizationSearchTerm);
        $entityManager->persist($adminUserEntity);
        $entityManager->persist($firstOrganizationEntity);
        $entityManager->persist($secondOrganizationEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/autocomplete/organization_autocomplete_type?query=' . urlencode($firstOrganizationSearchTerm));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($firstOrganizationEntity->getName(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($secondOrganizationEntity->getName(), (string) $client->getResponse()->getContent());

        $client->request('GET', '/autocomplete/organization_autocomplete_type?query=' . urlencode($secondOrganizationSearchTerm));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($secondOrganizationEntity->getName(), (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($firstOrganizationEntity->getName(), (string) $client->getResponse()->getContent());
    }

    public function testItDoesNotPreloadUnfilteredChoicesBeforeSearching(): void
    {
        $form = static::getContainer()
            ->get(FormFactoryInterface::class)
            ->create(OrganizationAutocompleteType::class);

        $attributes = $form->createView()->vars['attr'];

        self::assertSame('false', $attributes['data-symfony--ux-autocomplete--autocomplete-preload-value']);
        self::assertSame(2, $attributes['data-symfony--ux-autocomplete--autocomplete-min-characters-value']);
    }
}
