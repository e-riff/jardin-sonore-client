<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterRendererInterface;
use App\Application\Mailing\RenderedNewsletter;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingRecommendation;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class TwigNewsletterRenderer implements NewsletterRendererInterface
{
    private const DEFAULT_BANNER_IMAGE_PATH = 'images/mailing/default-banner.webp';

    public function __construct(
        private Environment $twig,
        #[Autowire('%env(default:app.mailing.from_email:DEFAULT_CONTACT)%')]
        private string $contactEmail,
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

        $context = [
            'campaign' => $mailingCampaign,
            'activeRecommendations' => $activeRecommendations,
            'preheader' => $mailingCampaign->getEmailSubject(),
            'unsubscribeUrl' => "mailto:{$this->contactEmail}?subject=" . rawurlencode('Desinscription newsletter'),
            'contactEmail' => $this->contactEmail,
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
