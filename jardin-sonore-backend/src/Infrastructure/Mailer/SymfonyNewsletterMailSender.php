<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\RenderedNewsletter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\EmailContactRepositoryInterface;
use App\Infrastructure\Mailing\TwigNewsletterRenderer;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SymfonyNewsletterMailSender implements NewsletterMailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailContactRepositoryInterface $emailContactRepository,
        #[Autowire('%env(default:app.mailing.from_email:DEFAULT_CONTACT)%')]
        private string $fromEmail,
        #[Autowire('%env(default:app.mailing.from_name:MAILING_FROM_NAME)%')]
        private string $fromName,
    ) {
    }

    public function sendTest(RenderedNewsletter $renderedNewsletter, string $recipientEmail): void
    {
        [$html, $text] = $this->personalizeUnsubscribeUrl($renderedNewsletter, $recipientEmail);

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($recipientEmail)
            ->subject($renderedNewsletter->subject)
            ->html($html);

        if (null !== $text && '' !== trim($text)) {
            $email->text($text);
        }

        $this->mailer->send($email);
    }

    public function sendToRecipient(RenderedNewsletter $renderedNewsletter, NewsletterRecipient $newsletterRecipient): void
    {
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($newsletterRecipient->getEmailAddress()->value())
            ->subject($renderedNewsletter->subject)
            ->html(str_replace(
                TwigNewsletterRenderer::UNSUBSCRIBE_TOKEN_PLACEHOLDER,
                $newsletterRecipient->getUnsubscribeToken(),
                $renderedNewsletter->html,
            ));

        if (null !== $renderedNewsletter->text && '' !== trim($renderedNewsletter->text)) {
            $email->text(str_replace(
                TwigNewsletterRenderer::UNSUBSCRIBE_TOKEN_PLACEHOLDER,
                $newsletterRecipient->getUnsubscribeToken(),
                $renderedNewsletter->text,
            ));
        }

        $this->mailer->send($email);
    }

    /**
     * @return array{string, ?string}
     */
    private function personalizeUnsubscribeUrl(RenderedNewsletter $renderedNewsletter, string $recipientEmail): array
    {
        try {
            $emailContact = $this->emailContactRepository->findByEmailAddress(new EmailAddress(mb_strtolower(trim($recipientEmail))));
        } catch (InvalidArgumentException) {
            return [$renderedNewsletter->html, $renderedNewsletter->text];
        }

        if (null === $emailContact) {
            return [$renderedNewsletter->html, $renderedNewsletter->text];
        }

        return [
            str_replace(TwigNewsletterRenderer::UNSUBSCRIBE_TOKEN_PLACEHOLDER, $emailContact->getUnsubscribeToken(), $renderedNewsletter->html),
            null === $renderedNewsletter->text
                ? null
                : str_replace(TwigNewsletterRenderer::UNSUBSCRIBE_TOKEN_PLACEHOLDER, $emailContact->getUnsubscribeToken(), $renderedNewsletter->text),
        ];
    }
}
