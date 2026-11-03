<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DirectorySourcesTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testOrganizationShowsItsImportSource(): void
    {
        $client = static::createClient(['environment' => 'test']);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $admin = (new AdminUserEntity())->setEmail("sources-{$suffix}@example.test")->setPassword('unused');
        $organization = (new OrganizationEntity())->setName("Lieu {$suffix}");
        $link = (new DirectoryImportLinkEntity())->setDirectoryEntry($organization)->setSource('culture-lieux')->setExternalId("lieu-{$suffix}")->setPayloadHash(str_repeat('a', 64));
        foreach ([$admin, $organization, $link] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($admin);
        $client->request('GET', '/directory?selectedId=' . $organization->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("lieu-{$suffix}", (string) $client->getResponse()->getContent());
    }
}
