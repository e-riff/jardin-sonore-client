<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Application\Mailing\CreateNewsletterRecommendation;
use App\Application\Mailing\CreateNewsletterRecommendationInput;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Model\Mailing\NewsletterRecommendationUsage;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;

final class NewsletterRecommendationCatalogControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testCatalogShowsStoredUsageAndDefaultsToNewestCreation(): void
    {
        $client = static::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $adminUserEntity = (new AdminUserEntity())->setEmail("history-{$suffix}@example.test")->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);
        $repository = self::getContainer()->get(NewsletterRecommendationRepositoryInterface::class);
        $older = new NewsletterRecommendation("{$suffix} Z ancien", 'Ancien texte', createdAt: new DateTimeImmutable('2026-01-01'), updatedAt: new DateTimeImmutable('2026-03-01'));
        $newer = new NewsletterRecommendation("{$suffix} A récent", 'Nouveau texte', createdAt: new DateTimeImmutable('2026-02-01'), updatedAt: new DateTimeImmutable('2026-02-01'));
        $repository->save($older);
        $repository->save($newer);
        self::getContainer()->get(NewsletterRecommendationUsageRepositoryInterface::class)->save(new NewsletterRecommendationUsage($older->getUuid(), Uuid::v7(), 'Campagne historique', new DateTimeImmutable('2026-02-15 10:00:00')));
        $client->request('GET', '/mailing/recommendations', ['query' => $suffix]);
        self::assertResponseIsSuccessful();
        $rows = $client->getCrawler()->filterXPath('//tbody/tr');
        self::assertCount(2, $rows);
        self::assertStringContainsString($newer->getTitle(), $rows->eq(0)->text());
        self::assertStringContainsString('01/02/2026', $rows->eq(0)->text());
        self::assertStringContainsString('Campagne historique', $rows->eq(1)->text());
        self::assertStringNotContainsString('Jamais envoyée', $rows->eq(1)->text());
        $client->request('GET', '/mailing/recommendations', ['query' => $suffix, 'sort' => 'createdAt', 'direction' => 'asc']);
        self::assertStringContainsString($older->getTitle(), $client->getCrawler()->filterXPath('//tbody/tr')->eq(0)->text());
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
        self::assertCount(1, $client->getCrawler()->filterXPath('//div[@data-controller="recommendation-text" and @data-recommendation-text-limit-value="420"]//textarea[@name="newsletter_recommendation[text]" and not(@maxlength)]'));

        $mailingCampaign = static::getContainer()->get(CreateMailingCampaign::class)(new CreateMailingCampaignInput(campaignType: MailingCampaignType::NEWSLETTER,
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
        self::assertCount(1, $client->getCrawler()->filterXPath('//div[@data-controller="recommendation-text" and @data-recommendation-text-limit-value="420"]//textarea[@name="newsletter_recommendation[text]" and not(@maxlength)]'));

        $newsletterRecommendation = static::getContainer()->get(CreateNewsletterRecommendation::class)(new CreateNewsletterRecommendationInput(
            title: 'Recommandation test',
            tag: null,
            text: "Texte test\nSuite",
            url: null,
            linkLabel: null,
            imageFile: null,
            active: true,
        ));
        $client->request('GET', '/mailing/recommendations/' . $newsletterRecommendation->getUuid()->toRfc4122() . '/edit');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $client->getCrawler()->filterXPath('//input[@name="newsletter_recommendation[imageFile]"]'));
        self::assertCount(1, $client->getCrawler()->filterXPath('//div[@data-controller="recommendation-text" and @data-recommendation-text-limit-value="420"]//textarea[@name="newsletter_recommendation[text]" and not(@maxlength)]'));
        $editor = $client->getCrawler()->filterXPath('//div[@data-recommendation-text-target="editor"]');
        self::assertSame('Texte testSuite', $editor->getNode(0)->textContent);
        self::assertCount(1, $editor->filterXPath('.//br'));
        $client->request('GET', '/mailing/recommendations');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $client->getCrawler()->filterXPath('//tr[@data-controller="catalog-row" and contains(@data-catalog-row-url-value, "' . $newsletterRecommendation->getUuid()->toRfc4122() . '")]'));
    }
}
