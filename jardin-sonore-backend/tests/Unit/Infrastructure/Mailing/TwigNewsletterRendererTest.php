<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mailing;

use App\Application\Mailing\RenderedNewsletter;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Infrastructure\Mailing\NewsletterMainTextFormatter;
use App\Infrastructure\Mailing\TwigNewsletterRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwigNewsletterRendererTest extends TestCase
{
    public function testItUsesTheSubtitleAsTheEmailPreheaderInsteadOfRepeatingTheSubject(): void
    {
        $renderedNewsletter = $this->renderCampaign(
            emailSubject: 'C’est la rentrée au Jardin Sonore',
            subtitle: 'Des nouvelles pour la rentrée',
            mainText: 'Une introduction qui ne doit pas être utilisée ici.',
        );

        self::assertMatchesRegularExpression('/display:none;">\s*Des nouvelles pour la rentrée/', $renderedNewsletter->html);
        self::assertDoesNotMatchRegularExpression('/display:none;">\s*C’est la rentrée au Jardin Sonore/', $renderedNewsletter->html);
    }

    public function testItUsesThePlainTextIntroductionAsTheFallbackPreheader(): void
    {
        $renderedNewsletter = $this->renderCampaign(
            emailSubject: 'Objet à ne pas répéter',
            subtitle: null,
            mainText: 'Une <strong>introduction</strong> musicale<br>pour la rentrée.',
        );

        self::assertStringContainsString('Une introduction musicale pour la rentrée.', $renderedNewsletter->html);
        self::assertStringNotContainsString('Une &lt;strong&gt;introduction&lt;/strong&gt;', $renderedNewsletter->html);
    }

    private function renderCampaign(string $emailSubject, ?string $subtitle, string $mainText): RenderedNewsletter
    {
        $twig = new Environment(new FilesystemLoader(__DIR__ . '/../../../../templates'));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => "/{$path}"));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => "https://example.test{$path}"));
        $twig->addFilter(new TwigFilter('trans', static fn (string $value): string => $value));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.test/newsletter/unsubscribe/__unsubscribe_token__');

        $mailingCampaign = new MailingCampaign(
            internalTitle: 'Campagne de test',
            emailSubject: $emailSubject,
            publicTitle: 'Titre public',
            mainText: $mainText,
            templateKey: 'default',
            audienceFilter: NewsletterAudienceFilter::empty(),
            subtitle: $subtitle,
        );

        return (new TwigNewsletterRenderer($twig, $urlGenerator, new NewsletterMainTextFormatter()))->render($mailingCampaign);
    }
}
