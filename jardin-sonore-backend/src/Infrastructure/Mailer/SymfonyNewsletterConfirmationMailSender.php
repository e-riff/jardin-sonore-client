<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Mailing\NewsletterConfirmationMailSenderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class SymfonyNewsletterConfirmationMailSender implements NewsletterConfirmationMailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        #[Autowire('%app.portal.public_base_url%')] private string $publicBaseUrl,
        #[Autowire('%app.mailing.from_email%')] private string $fromEmail,
        #[Autowire('%app.mailing.from_name%')] private string $fromName,
    ) {
    }

    public function sendConfirmation(string $emailAddress, string $rawToken): void
    {
        $context = [
            'emailSubject' => $this->translator->trans('confirmation.subject', [], 'newsletter', 'fr'),
            'preheader' => $this->translator->trans('confirmation.preheader', [], 'newsletter', 'fr'),
            'firstName' => '',
            'actionUrl' => rtrim($this->publicBaseUrl, '/') . '/newsletter/confirmer/confirmation#' . rawurlencode($rawToken),
            'actionLabel' => $this->translator->trans('confirmation.action', [], 'newsletter', 'fr'),
            'footerNotice' => $this->translator->trans('confirmation.notice', [], 'newsletter', 'fr'),
        ];
        $this->mailer->send((new Email())->from(new Address($this->fromEmail, $this->fromName))->to($emailAddress)
            ->subject($context['emailSubject'])
            ->html($this->twig->render('newsletter/confirmation_email.html.twig', $context))
            ->text($this->twig->render('newsletter/confirmation_email.txt.twig', $context)));
    }
}
