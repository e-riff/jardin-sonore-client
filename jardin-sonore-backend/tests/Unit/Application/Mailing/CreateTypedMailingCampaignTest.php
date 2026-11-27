<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Application\Mailing\MailingIntroBlockProcessor;
use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Application\Storage\MailingIntroImageStorageInterface;
use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreateTypedMailingCampaignTest extends TestCase
{
    public function testNewsletterCannotBeCreatedWithoutItsConfiguredMask(): void
    {
        $storage = $this->createStub(MailingIntroImageStorageInterface::class);
        $create = new CreateMailingCampaign($this->createStub(MailingCampaignRepositoryInterface::class), $this->createStub(MailingBannerImageStorageInterface::class), new MailingIntroBlockProcessor($storage), $storage);
        $this->expectException(InvalidArgumentException::class);
        $create(new CreateMailingCampaignInput('Internal', 'Subject', 'Title', 'Editorial', null, null, null, null, campaignType: MailingCampaignType::NEWSLETTER));
    }

    public function testNewOutreachUsesConfiguredDefaultWindow(): void
    {
        $imageStorage = $this->createStub(MailingIntroImageStorageInterface::class);
        $create = new CreateMailingCampaign($this->createStub(MailingCampaignRepositoryInterface::class), $this->createStub(MailingBannerImageStorageInterface::class), new MailingIntroBlockProcessor($imageStorage), $imageStorage, 9, $this->configuredMaskRepository());
        $campaign = $create(new CreateMailingCampaignInput('Internal', 'Subject', 'Title', 'Proposal', null, null, null, null, campaignType: MailingCampaignType::OUTREACH));
        self::assertSame(9, $campaign->getOutreachExclusionWeeks());
        $newsletter = $create(new CreateMailingCampaignInput('Internal', 'Subject', 'Title', 'Editorial', null, null, null, null, campaignType: MailingCampaignType::NEWSLETTER));
        self::assertSame(0, $newsletter->getOutreachExclusionWeeks());
    }

    public function testNewsletterInitializesAllFourSectionsAndKeepsItsCallToActionVisible(): void
    {
        $imageStorage = $this->createStub(MailingIntroImageStorageInterface::class);
        $create = new CreateMailingCampaign($this->createStub(MailingCampaignRepositoryInterface::class), $this->createStub(MailingBannerImageStorageInterface::class), new MailingIntroBlockProcessor($imageStorage), $imageStorage, maskRepository: $this->configuredMaskRepository());
        $campaign = $create(new CreateMailingCampaignInput('Internal', 'Subject', 'Title', 'Editorial', null, 'Contact us', 'https://example.test', null, campaignType: MailingCampaignType::NEWSLETTER));
        self::assertSame(['editorial', 'monthly_idea', 'jardin_sonore', 'recommendations'], array_keys($campaign->getStructuredContent()->toArray()['sections']));
        self::assertTrue($campaign->getStructuredContent()->isSectionEnabled('jardin_sonore'));
    }

    private function configuredMaskRepository(): MailingAudienceMaskRepositoryInterface
    {
        $repository = $this->createStub(MailingAudienceMaskRepositoryInterface::class);
        $repository->method('findNewsletterMask')->willReturn(new MailingAudienceMask('Newsletter', new NewsletterAudienceFilter(departmentCodes: ['42'])));

        return $repository;
    }
}
