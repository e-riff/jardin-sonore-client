<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\RenderedNewsletter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SymfonyNewsletterMailSender implements NewsletterMailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(default:app.mailing.from_email:DEFAULT_CONTACT)%')]
        private string $fromEmail,
        #[Autowire('%env(default:app.mailing.from_name:MAILING_FROM_NAME)%')]
        private string $fromName,
    ) {
    }

    public function sendTest(RenderedNewsletter $renderedNewsletter, string $recipientEmail): void
    {
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($recipientEmail)
            ->subject($renderedNewsletter->subject)
            ->html($renderedNewsletter->html);

        if (null !== $renderedNewsletter->text && '' !== trim($renderedNewsletter->text)) {
            $email->text($renderedNewsletter->text);
        }

        $this->mailer->send($email);
    }
}
