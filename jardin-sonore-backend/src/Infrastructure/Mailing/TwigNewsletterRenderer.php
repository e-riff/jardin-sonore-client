<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\RenderedNewsletter;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingRecommendation;
use InvalidArgumentException;
use Twig\Environment;

final readonly class TwigNewsletterRenderer implements NewsletterRendererInterface
{
    public function __construct(private Environment $twig)
    {
    }

    public function render(MailingCampaign $mailingCampaign): RenderedNewsletter
    {
        $template = $this->resolveTemplate($mailingCampaign->getTemplateKey());
        $activeRecommendations = array_values(array_filter(
            $mailingCampaign->getRecommendations(),
            static fn (MailingRecommendation $mailingRecommendation): bool => $mailingRecommendation->isActive(),
        ));
        $heroImagePath = $mailingCampaign->getBannerImagePath();

        $context = [
            'campaign' => $mailingCampaign,
            'activeRecommendations' => $activeRecommendations,
            'preheader' => $mailingCampaign->getEmailSubject(),
            'unsubscribeUrl' => '#unsubscribe-preview',
            'heroImagePath' => $heroImagePath,
        ];

        return new RenderedNewsletter(
            subject: $mailingCampaign->getEmailSubject(),
            html: $this->twig->render($template, $context),
            text: $this->twig->render('mailing/email/default.txt.twig', $context),
            bannerImagePath: $heroImagePath,
        );
    }

    private function resolveTemplate(string $templateKey): string
    {
        return match ($templateKey) {
            'default' => 'mailing/email/default.html.twig',
            default => throw new InvalidArgumentException("Unknown mailing template key \"{$templateKey}\"."),
        };
    }
}
