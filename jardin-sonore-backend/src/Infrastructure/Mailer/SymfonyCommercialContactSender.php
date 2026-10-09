<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Commercial\CommercialContactMailSenderInterface;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class SymfonyCommercialContactSender implements CommercialContactMailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        #[Autowire('%env(DEFAULT_CONTACT)%')] private string $contactEmail,
    ) {
    }

    public function send(CommercialRequestEntity $requestEntity): void
    {
        $subject = $this->translator->trans('subject', ['%name%' => $requestEntity->getSenderName()], 'commercial_email', 'fr');
        $this->mailer->send(
            (new Email())
                ->from($this->contactEmail)
                ->to($this->contactEmail)
                ->replyTo(new Address($requestEntity->getEmailAddress(), $requestEntity->getSenderName()))
                ->subject($subject)
                ->text($this->twig->render('commercial/contact_email.txt.twig', ['request' => $requestEntity])),
        );
    }
}
