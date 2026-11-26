<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class MailingCampaignContentControllerTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
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
        $campaign = (static::getContainer()->get(CreateMailingCampaign::class))(new CreateMailingCampaignInput(
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
