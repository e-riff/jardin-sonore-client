<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Application\Mailing\CreateNewsletterRecommendation;
use App\Application\Mailing\CreateNewsletterRecommendationInput;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class NewsletterRecommendationCatalogControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testRecommendationFormsRenderWithImageField(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $adminUserEntity = (new AdminUserEntity())
            ->setEmail('recommendation-form-' . bin2hex(random_bytes(5)) . '@example.test')
            ->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        $client->request('GET', '/mailing/recommendations/new');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $client->getCrawler()->filterXPath('//input[@name="newsletter_recommendation[imageFile]"]'));

        $mailingCampaign = static::getContainer()->get(CreateMailingCampaign::class)(new CreateMailingCampaignInput(
            internalTitle: 'Campagne test',
            emailSubject: 'Objet test',
            publicTitle: 'Titre test',
            mainText: 'Contenu test',
            subtitle: null,
            callToActionLabel: null,
            callToActionUrl: null,
            bannerImageFile: null,
        ));
        $client->request('GET', '/mailing/recommendations/new?campaign=' . $mailingCampaign->getUuid()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $client->getCrawler()->filterXPath('//input[@name="newsletter_recommendation[imageFile]"]'));

        $newsletterRecommendation = static::getContainer()->get(CreateNewsletterRecommendation::class)(new CreateNewsletterRecommendationInput(
            title: 'Recommandation test',
            tag: null,
            text: 'Texte test',
            url: null,
            linkLabel: null,
            imageFile: null,
            active: true,
        ));
        $client->request('GET', '/mailing/recommendations/' . $newsletterRecommendation->getUuid()->toRfc4122() . '/edit');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $client->getCrawler()->filterXPath('//input[@name="newsletter_recommendation[imageFile]"]'));
    }
}
