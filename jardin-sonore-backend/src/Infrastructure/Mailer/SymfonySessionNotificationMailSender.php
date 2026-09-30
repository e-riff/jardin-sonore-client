<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Session\SessionNotificationMailSenderInterface;
use App\Application\Session\SessionNotificationMailView;
use IntlDateFormatter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class SymfonySessionNotificationMailSender implements SessionNotificationMailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        #[Autowire('%app.portal.public_base_url%')]
        private string $publicBaseUrl,
        #[Autowire('%app.mailing.from_email%')]
        private string $fromEmail,
        #[Autowire('%app.mailing.from_name%')]
        private string $fromName,
    ) {
    }

    public function send(SessionNotificationMailView $sessionNotificationMailView): void
    {
        $publicBaseUrl = rtrim($this->publicBaseUrl, '/');
        $subject = $this->translator->trans('session.subject', ['%title%' => $sessionNotificationMailView->sessionTitle], 'service_email', 'fr');
        $dateFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, $sessionNotificationMailView->sessionDate->getTimezone(), null, 'd MMMM yyyy');
        $sessionDestination = '/portail/seances/' . rawurlencode($sessionNotificationMailView->sessionSlug);
        $context = [
            'emailSubject' => $subject,
            'preheader' => $this->translator->trans('session.preheader', [], 'service_email', 'fr'),
            'firstName' => $sessionNotificationMailView->firstName,
            'session' => $sessionNotificationMailView,
            'sessionDateLabel' => $dateFormatter->format($sessionNotificationMailView->sessionDate) ?: $sessionNotificationMailView->sessionDate->format('d/m/Y'),
            'actionUrl' => $publicBaseUrl . '/portail/connexion?next=' . rawurlencode($sessionDestination),
            'actionLabel' => $this->translator->trans('session.action', [], 'service_email', 'fr'),
            'footerNotice' => $this->translator->trans('session.notice', [], 'service_email', 'fr'),
            'preferencesUrl' => $publicBaseUrl . '/portail/compte',
        ];

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($sessionNotificationMailView->email)
                ->subject($subject)
                ->html($this->twig->render('session_notification/email.html.twig', $context))
                ->text($this->twig->render('session_notification/email.txt.twig', $context)),
        );
    }
}
