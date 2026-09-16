<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class SymfonyPortalAccountMailSender implements PortalAccountMailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
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
        $this->send($userEntity, $rawToken, 'Invitation à votre espace Jardin Sonore', 'invitation');
    }

    public function sendPasswordReset(UserEntity $userEntity, string $rawToken): void
    {
        $this->send($userEntity, $rawToken, 'Réinitialisez votre mot de passe Jardin Sonore', 'password_reset');
    }

    private function send(UserEntity $userEntity, string $rawToken, string $subject, string $type): void
    {
        $passwordLink = rtrim($this->publicBaseUrl, '/') . $this->urlGenerator->generate(
            'portal_password_set',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($userEntity->getEmail())
                ->subject($subject)
                ->html($this->twig->render('portal_password/email.html.twig', [
                    'passwordLink' => $passwordLink,
                    'type' => $type,
                ])),
        );
    }
}
