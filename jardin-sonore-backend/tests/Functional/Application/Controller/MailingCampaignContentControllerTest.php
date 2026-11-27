<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mailing\CreateMailingCampaignInput;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use App\Tests\Fixtures\CreatesMailingWithNewsletterMask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class MailingCampaignContentControllerTest extends WebTestCase
{
    use CreatesMailingWithNewsletterMask;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testJournalRequiresLoginAndValidatesDateRange(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mailing/outreach-journal');
        self::assertResponseRedirects('/login');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = (new AdminUserEntity())->setEmail('journal-' . bin2hex(random_bytes(6)) . '@example.test')->setPassword('unused');
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);
        $client->request('GET', '/mailing/outreach-journal');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Journal des démarchages', (string) $client->getResponse()->getContent());
        $client->request('GET', '/mailing/outreach-journal?since=2026-11-26&until=2026-10-01');
        self::assertResponseStatusCodeSame(422);
    }

    public function testOutreachDelayCanBeSavedWithoutSendingCampaign(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = (new AdminUserEntity())->setEmail('outreach-' . bin2hex(random_bytes(6)) . '@example.test')->setPassword('unused');
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);
        $campaign = ($this->configuredCampaignCreator())(new CreateMailingCampaignInput(
            internalTitle: 'Outreach test', emailSubject: 'Subject', publicTitle: 'Title', mainText: 'Proposal',
            subtitle: null, callToActionLabel: null, callToActionUrl: null, bannerImageFile: null, campaignType: MailingCampaignType::OUTREACH,
        ));
        $path = '/mailing/' . $campaign->getUuid()->toRfc4122() . '/send';
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer le délai')->form();
        $form['outreach_settings[weeks]'] = '9';
        $client->submit($form);
        self::assertResponseRedirects($path);
        $restored = static::getContainer()->get(MailingCampaignRepositoryInterface::class)->findByUuid($campaign->getUuid());
        self::assertSame(9, $restored->getOutreachExclusionWeeks());
        self::assertFalse($restored->hasDeliveryStarted());
    }

    public function testNewsletterEditorSavesSectionsWithoutLosingHiddenContent(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('_test', (string) $entityManager->getConnection()->getDatabase());
        $admin = (new AdminUserEntity())->setEmail('content-' . bin2hex(random_bytes(6)) . '@example.test')->setPassword('unused');
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);
        $campaign = ($this->configuredCampaignCreator())(new CreateMailingCampaignInput(
            internalTitle: 'Newsletter test', emailSubject: 'Subject', publicTitle: 'Title', mainText: 'Editorial to preserve',
            subtitle: null, callToActionLabel: null, callToActionUrl: null, bannerImageFile: null, campaignType: MailingCampaignType::NEWSLETTER,
        ));
        $crawler = $client->request('GET', '/mailing/' . $campaign->getUuid()->toRfc4122() . '/content');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['edit_mailing_campaign[sections][editorial][enabled]']->untick();
        $client->submit($form);
        self::assertResponseRedirects();
        $restored = static::getContainer()->get(MailingCampaignRepositoryInterface::class)->findByUuid($campaign->getUuid());
        self::assertNotNull($restored);
        self::assertFalse($restored->getStructuredContent()->isSectionEnabled('editorial'));
        self::assertSame('Editorial to preserve', $restored->getStructuredContent()->getBlocks('editorial')[0]['text']);
        self::assertSame('newsletter', $restored->getTemplateKey());
    }
}
