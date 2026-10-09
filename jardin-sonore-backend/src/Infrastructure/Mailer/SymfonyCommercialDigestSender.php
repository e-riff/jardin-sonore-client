<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Commercial\CommercialDigest;
use App\Application\Commercial\CommercialDigestSenderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class SymfonyCommercialDigestSender implements CommercialDigestSenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private TranslatorInterface $translator,
        #[Autowire('%env(DEFAULT_CONTACT)%')] private string $contactEmail,
    ) {
    }

    public function send(CommercialDigest $digest): void
    {
        $subject = $this->translator->trans('subject', ['%date%' => $digest->localDate->format('d/m/Y')], 'commercial_digest', 'fr');
        $this->mailer->send(
            (new Email())
                ->from($this->contactEmail)
                ->to($this->contactEmail)
                ->subject($subject)
                ->text($this->twig->render('commercial/digest_email.txt.twig', ['digest' => $digest]))
                ->html($this->twig->render('commercial/digest_email.html.twig', ['digest' => $digest])),
        );
    }
}
