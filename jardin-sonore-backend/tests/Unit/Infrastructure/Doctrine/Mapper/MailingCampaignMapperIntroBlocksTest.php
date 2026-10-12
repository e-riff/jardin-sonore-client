<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Infrastructure\Doctrine\Mapper\MailingCampaignMapper;
use App\Infrastructure\Doctrine\Mapper\MailingRecommendationMapper;
use App\Infrastructure\Doctrine\Mapper\NewsletterAudienceFilterArrayMapper;
use PHPUnit\Framework\TestCase;

final class MailingCampaignMapperIntroBlocksTest extends TestCase
{
    public function testItPreservesBlockOrderAndPreheaderAcrossEntityMapping(): void
    {
        $blocks = [
            ['id' => 'one', 'kind' => 'paragraph', 'text' => 'Bonjour'],
            ['id' => 'two', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/photo.webp', 'imageAlt' => 'Photo', 'imageDecorative' => false],
        ];
        $campaign = new MailingCampaign(
            internalTitle: 'Campagne',
            emailSubject: 'Objet',
            publicTitle: 'Titre',
            mainText: 'Bonjour',
            templateKey: 'default',
            audienceFilter: NewsletterAudienceFilter::empty(),
            preheader: 'Aperçu',
            introBlocks: $blocks,
        );
        $mapper = new MailingCampaignMapper(new MailingRecommendationMapper(), new NewsletterAudienceFilterArrayMapper());

        $mappedCampaign = $mapper->toDomain($mapper->toEntity($campaign));

        self::assertSame($blocks, $mappedCampaign->getIntroBlocks());
        self::assertSame('Aperçu', $mappedCampaign->getPreheader());
    }
}
