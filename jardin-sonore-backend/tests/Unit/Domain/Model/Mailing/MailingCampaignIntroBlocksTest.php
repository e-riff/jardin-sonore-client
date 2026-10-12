<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MailingCampaignIntroBlocksTest extends TestCase
{
    public function testLegacyCampaignKeepsNullBlocksAndPreheader(): void
    {
        $campaign = $this->campaign();

        self::assertNull($campaign->getIntroBlocks());
        self::assertNull($campaign->getPreheader());
        self::assertSame('Ancienne introduction', $campaign->getMainText());
    }

    public function testNewBlocksKeepTheirOrderAndPreheader(): void
    {
        $blocks = [
            ['id' => 'first', 'kind' => 'paragraph', 'text' => 'Premier paragraphe'],
            ['id' => 'photo', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/photo.webp', 'imageAlt' => 'Atelier', 'imageDecorative' => false],
            ['id' => 'last', 'kind' => 'paragraph', 'text' => 'Dernier paragraphe'],
        ];
        $campaign = $this->campaign(introBlocks: $blocks, preheader: 'Aperçu distinct');

        self::assertSame($blocks, $campaign->getIntroBlocks());
        self::assertSame('Aperçu distinct', $campaign->getPreheader());
    }

    public function testBlocksRequireANonemptyParagraph(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->campaign(introBlocks: [
            ['id' => 'photo', 'kind' => 'image', 'imagePath' => 'uploads/mailing/content/photo.webp', 'imageAlt' => 'Atelier', 'imageDecorative' => false],
        ]);
    }

    /** @param list<array<string, mixed>>|null $introBlocks */
    private function campaign(?array $introBlocks = null, ?string $preheader = null): MailingCampaign
    {
        return new MailingCampaign(
            internalTitle: 'Campagne',
            emailSubject: 'Nouvelles',
            publicTitle: 'Jardin Sonore',
            mainText: 'Ancienne introduction',
            templateKey: 'default',
            audienceFilter: NewsletterAudienceFilter::empty(),
            preheader: $preheader,
            introBlocks: $introBlocks,
        );
    }
}
