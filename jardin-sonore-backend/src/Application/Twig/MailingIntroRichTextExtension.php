<?php

declare(strict_types=1);

namespace App\Application\Twig;

use App\Infrastructure\Mailing\NewsletterRichTextFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MailingIntroRichTextExtension extends AbstractExtension
{
    public function __construct(private readonly NewsletterRichTextFormatter $newsletterRichTextFormatter)
    {
    }

    public function getFilters(): array
    {
        return [new TwigFilter('mailing_intro_rich_text', fn (string $text): string => $this->newsletterRichTextFormatter->format($text)->html, ['is_safe' => ['html']])];
    }
}
