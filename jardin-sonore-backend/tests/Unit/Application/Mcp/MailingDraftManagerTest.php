<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\MailingDraftManager;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Mailing\NewsletterRichTextFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MailingDraftManagerTest extends TestCase
{
    public function testSentCampaignCanBeReadButCannotBeEdited(): void
    {
        $campaign = $this->campaign();
        $campaign->markDeliveryQueued();
        $repository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($campaign);
        $repository->expects(self::never())->method('save');
        $manager = new MailingDraftManager($repository, $this->createStub(MailingAudienceMaskRepositoryInterface::class), new NewsletterRichTextFormatter());

        self::assertFalse($manager->get((string) $campaign->getUuid())['editable']);
        self::assertSame('delivery_queued', $manager->get((string) $campaign->getUuid())['status']);
        $this->expectException(InvalidArgumentException::class);
        $manager->update((string) $campaign->getUuid(), ['revision' => $manager->get((string) $campaign->getUuid())['revision'], 'emailSubject' => 'Nouveau sujet']);
    }

    public function testCreateAndUpdateTextKeepsExistingImageBlocks(): void
    {
        $campaign = $this->campaign();
        $campaign->updateContent(
            'Interne', 'Sujet', 'Titre', 'Texte', 'default', null, null, null, null,
            introBlocks: [
                ['id' => 'p1', 'kind' => 'paragraph', 'text' => 'Avant'],
                ['id' => 'i1', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/photo.jpg', 'imageAlt' => 'Photo', 'imageDecorative' => false],
            ],
        );
        $repository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($campaign);
        $repository->expects(self::once())->method('save');
        $manager = new MailingDraftManager($repository, $this->createStub(MailingAudienceMaskRepositoryInterface::class), new NewsletterRichTextFormatter());

        $result = $manager->update((string) $campaign->getUuid(), [
            'revision' => $manager->get((string) $campaign->getUuid())['revision'],
            'introBlocks' => [
                ['id' => 'p1', 'kind' => 'paragraph', 'text' => '<strong>Après</strong>'],
                ['id' => 'i1', 'kind' => 'image'],
            ],
        ]);

        self::assertSame('uploads/mailing/content/photo.jpg', $campaign->getIntroBlocks()[1]['imagePath']);
        self::assertSame('<strong>Après</strong>', $campaign->getIntroBlocks()[0]['text']);
        self::assertSame('Après', $result['mainText']);
    }

    public function testStaleRevisionCannotOverwriteChanges(): void
    {
        $campaign = $this->campaign();
        $repository = $this->createMock(MailingCampaignRepositoryInterface::class);
        $repository->method('findByUuid')->willReturn($campaign);
        $repository->expects(self::never())->method('save');
        $manager = new MailingDraftManager($repository, $this->createStub(MailingAudienceMaskRepositoryInterface::class), new NewsletterRichTextFormatter());

        $this->expectException(InvalidArgumentException::class);
        $manager->update((string) $campaign->getUuid(), ['revision' => str_repeat('0', 64), 'emailSubject' => 'Autre']);
    }

    private function campaign(): MailingCampaign
    {
        return new MailingCampaign('Interne', 'Sujet', 'Titre', 'Texte', 'default', NewsletterAudienceFilter::empty(), uuid: Uuid::v4());
    }
}
