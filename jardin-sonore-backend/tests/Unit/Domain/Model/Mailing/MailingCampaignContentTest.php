<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignContent;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingRecommendation;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MailingCampaignContentTest extends TestCase
{
    public function testLegacyContentCannotSelectATemplateThatWouldHideItsBody(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MailingCampaign('Internal', 'Subject', 'Title', 'Legacy introduction', 'newsletter', NewsletterAudienceFilter::empty());
    }

    public function testCompatibilityTextFollowsVisibleSectionsInsteadOfTheInitialBody(): void
    {
        $campaign = $this->campaign(MailingCampaignType::NEWSLETTER, MailingCampaignContent::fromArray([
            'version' => 1, 'sections' => ['editorial' => ['enabled' => true, 'blocks' => [['id' => 'e', 'kind' => 'paragraph', 'text' => 'Current editorial']]]],
        ]));
        self::assertSame('Current editorial', $campaign->getMainText());
        $campaign->setStructuredContent(MailingCampaignContent::fromArray([
            'version' => 1, 'sections' => ['editorial' => ['enabled' => false, 'blocks' => [['id' => 'e', 'kind' => 'paragraph', 'text' => 'Current editorial']]]],
        ]));
        self::assertSame('', $campaign->getMainText());
    }

    public function testHiddenSectionsKeepContentButDoNotExposeRecommendations(): void
    {
        $content = MailingCampaignContent::fromArray(['version' => 1, 'sections' => [
            'editorial' => ['enabled' => true, 'blocks' => [['id' => 'e', 'kind' => 'paragraph', 'text' => 'Écouter']]],
            'recommendations' => ['enabled' => false, 'blocks' => []],
        ]]);
        $campaign = $this->campaign(MailingCampaignType::NEWSLETTER, $content);
        $campaign->replaceRecommendations([new MailingRecommendation('Livre', null, 'Lecture', 1)]);
        self::assertSame([], $campaign->getVisibleRecommendations());
        self::assertSame('Écouter', $content->getBlocks('editorial')[0]['text']);
        self::assertFalse($content->isSectionEnabled('recommendations'));
        $campaign->assertReadyForDelivery();
    }

    public function testRecommendationsAloneCanMakeNewsletterUseful(): void
    {
        $campaign = $this->campaign(MailingCampaignType::NEWSLETTER, MailingCampaignContent::fromArray([
            'version' => 1, 'sections' => ['recommendations' => ['enabled' => true, 'blocks' => []]],
        ]));
        $campaign->replaceRecommendations([new MailingRecommendation('Livre', null, 'Lecture', 1)]);
        $campaign->assertReadyForDelivery();
        self::assertCount(1, $campaign->getVisibleRecommendations());
    }

    public function testMarkupOnlyDoesNotMakeAnEmailSendable(): void
    {
        $campaign = $this->campaign(MailingCampaignType::NEWSLETTER, MailingCampaignContent::fromArray([
            'version' => 1, 'sections' => ['editorial' => ['enabled' => true, 'blocks' => [['id' => 'e', 'kind' => 'paragraph', 'text' => '<p>&nbsp;<br></p>']]]],
        ]));
        $this->expectException(InvalidArgumentException::class);
        $campaign->assertReadyForDelivery();
    }

    public function testOutreachRequiresAProposalEvenWithRecommendations(): void
    {
        $campaign = $this->campaign(MailingCampaignType::OUTREACH, MailingCampaignContent::fromArray([
            'version' => 1, 'sections' => ['recommendations' => ['enabled' => true, 'blocks' => []]],
        ]));
        $campaign->replaceRecommendations([new MailingRecommendation('Livre', null, 'Lecture', 1)]);
        $this->expectException(InvalidArgumentException::class);
        $campaign->assertReadyForDelivery();
    }

    public function testScriptOnlyContentCannotMakeANewsletterSendable(): void
    {
        $campaign = $this->campaign(MailingCampaignType::NEWSLETTER, MailingCampaignContent::fromArray([
            'version' => 1, 'sections' => ['editorial' => ['enabled' => true, 'blocks' => [['id' => 'e', 'kind' => 'paragraph', 'text' => '<script>alert(1)</script>']]]],
        ]));
        $this->expectException(InvalidArgumentException::class);
        $campaign->assertReadyForDelivery();
    }

    public function testUnknownVersionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MailingCampaignContent::fromArray(['version' => 2, 'sections' => []]);
    }

    public function testUnknownBlockKindIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MailingCampaignContent::fromArray(['version' => 1, 'sections' => [
            'editorial' => ['enabled' => true, 'blocks' => [['id' => 'e', 'kind' => 'script', 'text' => 'bad']]],
        ]]);
    }

    private function campaign(MailingCampaignType $type, MailingCampaignContent $content): MailingCampaign
    {
        return new MailingCampaign('Internal', 'Subject', 'Title', '', $type->value, NewsletterAudienceFilter::empty(), campaignType: $type, structuredContent: $content);
    }
}
