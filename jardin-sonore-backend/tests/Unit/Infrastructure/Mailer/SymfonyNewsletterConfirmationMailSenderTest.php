<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mailer;

use App\Infrastructure\Mailer\SymfonyNewsletterConfirmationMailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SymfonyNewsletterConfirmationMailSenderTest extends TestCase
{
    public function testConfirmationUsesPublicLinkAndBothMessageFormats(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function (Email $email): bool {
            self::assertSame('fixture@example.test', $email->getTo()[0]->getAddress());
            self::assertSame('bonjour@example.test', $email->getFrom()[0]->getAddress());
            self::assertStringContainsString('https://www.example.test/newsletter/confirmer/confirmation#test-token', (string) $email->getHtmlBody());
            self::assertStringContainsString('https://www.example.test/newsletter/confirmer/confirmation#test-token', (string) $email->getTextBody());

            return true;
        }));
        $sender = new SymfonyNewsletterConfirmationMailSender($mailer, new Environment(new ArrayLoader([
            'newsletter/confirmation_email.html.twig' => '{{ actionUrl }}',
            'newsletter/confirmation_email.txt.twig' => '{{ actionUrl }}',
        ])), new Translator('fr'), 'https://www.example.test/', 'bonjour@example.test', 'Jardin Sonore');
        $sender->sendConfirmation('fixture@example.test', 'test-token');
    }
}
