<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\RenderedNewsletter;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingRecommendation;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class TwigNewsletterRenderer implements NewsletterRendererInterface
{
    private const string DEFAULT_BANNER_IMAGE_PATH = 'images/mailing/default-banner.webp';
    public const string UNSUBSCRIBE_TOKEN_PLACEHOLDER = '__unsubscribe_token__';

    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private NewsletterMainTextFormatter $newsletterMainTextFormatter,
        private NewsletterRichTextFormatter $newsletterRichTextFormatter,
    ) {
    }

    public function render(MailingCampaign $mailingCampaign): RenderedNewsletter
    {
        $template = $this->resolveTemplate($mailingCampaign->getTemplateKey());
        $activeRecommendations = array_values(array_filter(
            $mailingCampaign->getRecommendations(),
            static fn (MailingRecommendation $mailingRecommendation): bool => $mailingRecommendation->isActive(),
        ));
        $heroImagePath = $mailingCampaign->getBannerImagePath() ?? self::DEFAULT_BANNER_IMAGE_PATH;
        $formattedMainText = $this->newsletterMainTextFormatter->format($mailingCampaign->getMainText());
        $introBlocks = $this->formatIntroBlocks($mailingCampaign->getIntroBlocks());
        $introText = null === $introBlocks
            ? $formattedMainText->text
            : implode("\n\n", array_values(array_filter(array_column($introBlocks, 'textPlain'))));
        $preheader = $mailingCampaign->getPreheader()
            ?? (null === $introBlocks
                ? $this->resolvePreheader($mailingCampaign, $formattedMainText->text)
                : $this->firstBlockPreheader($introBlocks));

        $unsubscribeUrl = $this->urlGenerator->generate('newsletter_unsubscribe', [
            'token' => self::UNSUBSCRIBE_TOKEN_PLACEHOLDER,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $context = [
            'campaign' => $mailingCampaign,
            'activeRecommendations' => $activeRecommendations,
            'preheader' => $preheader,
            'unsubscribeUrl' => $unsubscribeUrl,
            'heroImagePath' => $heroImagePath,
            'mainTextHtml' => $formattedMainText->html,
            'mainTextText' => $formattedMainText->text,
            'introBlocks' => $introBlocks,
            'introText' => $introText,
        ];

        return new RenderedNewsletter(
            subject: $mailingCampaign->getEmailSubject(),
            html: $this->twig->render($template, $context),
            text: $this->twig->render('mailing/email/default.txt.twig', $context),
            bannerImagePath: $heroImagePath,
            preheader: $preheader,
        );
    }

    /**
     * @param list<array<string, mixed>>|null $blocks
     *
     * @return list<array<string, mixed>>|null
     */
    private function formatIntroBlocks(?array $blocks): ?array
    {
        if (null === $blocks) {
            return null;
        }

        return array_map(function (array $block): array {
            $formattedText = $this->newsletterRichTextFormatter->format((string) ($block['text'] ?? ''));

            return array_merge($block, [
                'textHtml' => $formattedText->html,
                'textPlain' => $formattedText->text,
            ]);
        }, $blocks);
    }

    /** @param list<array<string, mixed>> $blocks */
    private function firstBlockPreheader(array $blocks): string
    {
        foreach ($blocks as $block) {
            if (in_array($block['kind'], ['paragraph', 'split'], true)) {
                $text = (string) $block['textPlain'];

                return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
            }
        }

        return '';
    }

    private function resolveTemplate(string $templateKey): string
    {
        return match ($templateKey) {
            'default' => 'mailing/email/default.html.twig',
            default => throw new InvalidArgumentException("Unknown mailing template key \"{$templateKey}\"."),
        };
    }

    private function resolvePreheader(MailingCampaign $mailingCampaign, string $plainTextIntroduction): string
    {
        $preheader = $mailingCampaign->getSubtitle() ?? $plainTextIntroduction;
        $preheader = html_entity_decode(strip_tags($preheader), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $preheader) ?? $preheader);
    }
}
