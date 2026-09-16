<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mailer;

use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Mailer\SymfonyPortalAccountMailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SymfonyPortalAccountMailSenderTest extends TestCase
{
    public function testItSendsAnInvitationWithThePublicPasswordLink(): void
    {
        $userEntity = (new UserEntity())->setEmail('contact@lilas.test');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email): bool {
                self::assertSame('Invitation à votre espace Jardin Sonore', $email->getSubject());
                self::assertSame('contact@lilas.test', $email->getTo()[0]->getAddress());
                self::assertStringContainsString('https://admin.jardin-sonore.local:8443/portail/definir-mot-de-passe/opaque-token', (string) $email->getHtmlBody());

                return true;
            }));
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/portail/definir-mot-de-passe/opaque-token');
        $portalAccountMailSender = new SymfonyPortalAccountMailSender(
            $mailer,
            new Environment(new ArrayLoader(['portal_password/email.html.twig' => '{{ passwordLink }}'])),
            $urlGenerator,
            'https://admin.jardin-sonore.local:8443',
            'bonjour@jardin-sonore.test',
            'Jardin Sonore',
        );

        $portalAccountMailSender->sendInvitation($userEntity, 'opaque-token');
    }
}
