<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Application\Mailing\MailingIntroBlockProcessor;
use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Application\Storage\MailingIntroImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateTypedMailingCampaignTest extends TestCase
{
    public function testNewsletterInitializesAllFourSectionsAndKeepsItsCallToActionVisible(): void
    {
        $imageStorage = $this->createStub(MailingIntroImageStorageInterface::class);
        $create = new CreateMailingCampaign($this->createStub(MailingCampaignRepositoryInterface::class), $this->createStub(MailingBannerImageStorageInterface::class), new MailingIntroBlockProcessor($imageStorage), $imageStorage);
        $campaign = $create(new CreateMailingCampaignInput('Internal', 'Subject', 'Title', 'Editorial', null, 'Contact us', 'https://example.test', null, campaignType: MailingCampaignType::NEWSLETTER));
        self::assertSame(['editorial', 'monthly_idea', 'jardin_sonore', 'recommendations'], array_keys($campaign->getStructuredContent()->toArray()['sections']));
        self::assertTrue($campaign->getStructuredContent()->isSectionEnabled('jardin_sonore'));
    }
}
