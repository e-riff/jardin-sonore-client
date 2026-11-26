<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Infrastructure\Doctrine\Mapper\MailingCampaignMapper;
use App\Infrastructure\Doctrine\Mapper\MailingRecommendationMapper;
use App\Infrastructure\Doctrine\Mapper\NewsletterAudienceFilterArrayMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MailingCampaignTypeTest extends TestCase
{
    public function testLegacyDraftMustBeClassifiedBeforeDeliveryWithoutConvertingContent(): void
    {
        $campaign = $this->campaign();
        self::assertNull($campaign->getCampaignType());
        $this->expectException(InvalidArgumentException::class);
        $campaign->assertReadyForDelivery();
    }

    public function testClassificationPreservesLegacyContentAndMappingDates(): void
    {
        $campaign = $this->campaign();
        $campaign->setCampaignType(MailingCampaignType::NEWSLETTER);
        $mapper = new MailingCampaignMapper(new MailingRecommendationMapper(), new NewsletterAudienceFilterArrayMapper());
        $restored = $mapper->toDomain($mapper->toEntity($campaign));
        self::assertSame(MailingCampaignType::NEWSLETTER, $restored->getCampaignType());
        self::assertSame('default', $restored->getTemplateKey());
        self::assertSame('Legacy content', $restored->getMainText());
        self::assertEquals($campaign->getUpdatedAt(), $restored->getUpdatedAt());
        $restored->assertReadyForDelivery();
    }

    public function testDeliveredCampaignCannotBeReclassified(): void
    {
        $campaign = $this->campaign();
        $campaign->setCampaignType(MailingCampaignType::OUTREACH);
        $campaign->markDeliveryQueued();
        $this->expectException(InvalidArgumentException::class);
        $campaign->setCampaignType(MailingCampaignType::NEWSLETTER);
    }

    public function testLegacyDeliveredCampaignRemainsUnclassifiedAndExtendable(): void
    {
        $campaign = $this->campaign();
        $campaign->markDeliverySent();
        $mapper = new MailingCampaignMapper(new MailingRecommendationMapper(), new NewsletterAudienceFilterArrayMapper());
        $restored = $mapper->toDomain($mapper->toEntity($campaign));
        self::assertNull($restored->getCampaignType());
        self::assertTrue($restored->canExtendAudience());
    }

    private function campaign(): MailingCampaign
    {
        return new MailingCampaign('Internal', 'Subject', 'Title', 'Legacy content', 'default', NewsletterAudienceFilter::empty());
    }
}
