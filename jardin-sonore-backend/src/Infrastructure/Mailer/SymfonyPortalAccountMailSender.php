<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class SymfonyPortalAccountMailSender implements PortalAccountMailSenderInterface
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

    public function sendInvitation(UserEntity $userEntity, string $rawToken): void
    {
        $this->send($userEntity, $rawToken, 'invitation');
    }

    public function sendPasswordReset(UserEntity $userEntity, string $rawToken): void
    {
        $this->send($userEntity, $rawToken, 'password_reset');
    }

    private function send(UserEntity $userEntity, string $rawToken, string $type): void
    {
        $passwordLink = rtrim($this->publicBaseUrl, '/') . '/portail/definir-mot-de-passe/' . rawurlencode($rawToken);
        $subject = $this->translator->trans("{$type}.subject", [], 'service_email', 'fr');
        $context = [
            'emailSubject' => $subject,
            'preheader' => $this->translator->trans("{$type}.preheader", [], 'service_email', 'fr'),
            'firstName' => $userEntity->getFirstName(),
            'type' => $type,
            'actionUrl' => $passwordLink,
            'actionLabel' => $this->translator->trans("{$type}.action", [], 'service_email', 'fr'),
            'footerNotice' => $this->translator->trans("{$type}.notice", [], 'service_email', 'fr'),
        ];

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($userEntity->getEmail())
                ->subject($subject)
                ->html($this->twig->render('portal_password/email.html.twig', $context))
                ->text($this->twig->render('portal_password/email.txt.twig', $context)),
        );
    }
}
