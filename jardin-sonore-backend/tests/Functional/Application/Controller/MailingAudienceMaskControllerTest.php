<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mailing\CreateMailingAudienceMask;
use App\Application\Mailing\CreateMailingAudienceMaskInput;
use App\Application\Mailing\GetMailingAudienceMask;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class MailingAudienceMaskControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testCatalogAndEditorShowFullMaskActionsAndCriteria(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('audience-mask-' . bin2hex(random_bytes(5)) . '@example.test')->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        $mailingAudienceMask = (static::getContainer()->get(CreateMailingAudienceMask::class))(
            new CreateMailingAudienceMaskInput('Masque de recette', new NewsletterAudienceFilter(includeFreeSubscribers: true)),
        );

        $client->request('GET', '/mailing/audience-masks');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/mailing/audience-masks/new', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('/mailing/audience-masks/' . $mailingAudienceMask->getUuid()->toRfc4122() . '/edit', (string) $client->getResponse()->getContent());

        $client->request('GET', '/mailing/audience-masks?query=aucun-resultat-possible');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('/mailing/audience-masks/' . $mailingAudienceMask->getUuid()->toRfc4122() . '/edit', (string) $client->getResponse()->getContent());

        $client->request('GET', '/mailing/audience-masks?query=recette');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/mailing/audience-masks/' . $mailingAudienceMask->getUuid()->toRfc4122() . '/edit', (string) $client->getResponse()->getContent());

        $client->request('GET', '/mailing/audience-masks/new');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Types d’établissement', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Vider les communes', (string) $client->getResponse()->getContent());

        $client->request('GET', '/mailing/audience-masks/' . $mailingAudienceMask->getUuid()->toRfc4122() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Masque de recette', (string) $client->getResponse()->getContent());
    }

    public function testDeletionRequiresCsrfAndRemovesMaskFromCatalog(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('audience-delete-' . bin2hex(random_bytes(5)) . '@example.test')->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        $mailingAudienceMask = (static::getContainer()->get(CreateMailingAudienceMask::class))(
            new CreateMailingAudienceMaskInput('Masque à supprimer', new NewsletterAudienceFilter(includeFreeSubscribers: true)),
        );
        $maskUuid = $mailingAudienceMask->getUuid()->toRfc4122();
        $deleteUrl = '/mailing/audience-masks/' . $maskUuid . '/delete';

        $client->request('POST', $deleteUrl, ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull((static::getContainer()->get(GetMailingAudienceMask::class))($mailingAudienceMask->getUuid()));

        $crawler = $client->request('GET', '/mailing/audience-masks/' . $maskUuid . '/edit');
        self::assertResponseIsSuccessful();
        $csrfToken = $crawler->filterXPath('//*[@data-confirmation-dialog-token-value]')->first()->attr('data-confirmation-dialog-token-value');
        self::assertNotNull($csrfToken);
        $client->request('POST', $deleteUrl, ['_token' => $csrfToken]);
        self::assertResponseRedirects('/mailing/audience-masks');
        self::assertNull((static::getContainer()->get(GetMailingAudienceMask::class))($mailingAudienceMask->getUuid()));
    }

    public function testCatalogDisplaysTranslatedOrganizationTypes(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())->setEmail('audience-types-' . bin2hex(random_bytes(5)) . '@example.test')->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        (static::getContainer()->get(CreateMailingAudienceMask::class))(
            new CreateMailingAudienceMaskInput('Masque MJC', new NewsletterAudienceFilter(organizationTypes: [OrganizationType::MJC])),
        );

        $client->request('GET', '/mailing/audience-masks?query=Masque%20MJC');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Types : MJC - maison pour tous', (string) $client->getResponse()->getContent());
    }
}
