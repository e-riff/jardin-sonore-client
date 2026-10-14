<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class MailingIndexControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testCampaignSearchAndStatusFilterKeepTheMatchingRow(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('mailing-index-' . bin2hex(random_bytes(5)) . '@example.test')
            ->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $uniqueWord = bin2hex(random_bytes(7));
        $mailingCampaign = (static::getContainer()->get(CreateMailingCampaign::class))(
            new CreateMailingCampaignInput(
                internalTitle: "Campagne {$uniqueWord}",
                emailSubject: 'Objet de recette',
                publicTitle: 'Titre de recette',
                mainText: 'Contenu de recette',
                subtitle: null,
                callToActionLabel: null,
                callToActionUrl: null,
                bannerImageFile: null,
            ),
        );
        $campaignUrl = '/mailing/' . $mailingCampaign->getUuid()->toRfc4122();

        $client->request('GET', "/mailing?query={$uniqueWord}&status=draft");
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($campaignUrl, (string) $client->getResponse()->getContent());

        $client->request('GET', "/mailing?query={$uniqueWord}&status=delivery_sent");
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString($campaignUrl, (string) $client->getResponse()->getContent());
    }
}
