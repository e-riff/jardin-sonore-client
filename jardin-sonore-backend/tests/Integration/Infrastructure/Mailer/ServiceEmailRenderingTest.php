<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailer;

use App\Application\Session\SessionNotificationMailView;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Mailer\SymfonyCommercialContactSender;
use App\Infrastructure\Mailer\SymfonyPortalAccountMailSender;
use App\Infrastructure\Mailer\SymfonySessionNotificationMailSender;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ServiceEmailRenderingTest extends TestCase
{
    private Environment $twig;
    private Translator $translator;

    protected function setUp(): void
    {
        $projectDirectory = dirname(__DIR__, 4);
        $this->translator = new Translator('fr');
        $this->translator->addLoader('yaml', new YamlFileLoader());
        $this->translator->addResource('yaml', "{$projectDirectory}/translations/service_email.fr.yaml", 'fr', 'service_email');
        $this->translator->addResource('yaml', "{$projectDirectory}/translations/commercial_email.fr.yaml", 'fr', 'commercial_email');
        $this->twig = new Environment(new FilesystemLoader("{$projectDirectory}/templates"), ['strict_variables' => true, 'autoescape' => 'name']);
        $this->twig->addExtension(new TranslationExtension($this->translator));
    }

    public function testSessionEmailsEscapeContentAndKeepSubjectPreheaderAndLinksDistinct(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function (Email $email): bool {
            $html = (string) $email->getHtmlBody();
            $text = (string) $email->getTextBody();
            self::assertSame('Nouvelle séance : Sons <script> & découvertes', $email->getSubject());
            self::assertStringContainsString('Une nouvelle séance vous attend dans votre espace Jardin Sonore.', $html);
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringContainsString('Sons &lt;script&gt; &amp; découvertes', $html);
            self::assertStringContainsString('Crèche &lt;Lilas&gt;', $html);
            self::assertStringContainsString('Sons <script> & découvertes', $text);
            self::assertStringContainsString('30 septembre 2026', $html);
            self::assertStringContainsString('https://jardin-sonore.test/portail/connexion?next=%2Fportail%2Fseances%2Fles-sons', $html);
            self::assertStringContainsString('https://jardin-sonore.test/portail/connexion?next=%2Fportail%2Fseances%2Fles-sons', $text);
            preg_match_all('/href="([^"]+)"/', $html, $matches);
            foreach ($matches[1] as $url) {
                self::assertSame('https', parse_url($url, PHP_URL_SCHEME));
                self::assertSame('jardin-sonore.test', parse_url($url, PHP_URL_HOST));
            }

            return true;
        }));
        $sender = new SymfonySessionNotificationMailSender($mailer, $this->twig, $this->translator, 'https://jardin-sonore.test/', 'bonjour@jardin-sonore.test', 'Jardin Sonore');
        $sender->send(new SessionNotificationMailView('client@portal.test', 'Camille <script>', 'Sons <script> & découvertes', new DateTimeImmutable('2026-09-30'), 'les-sons', ['Crèche <Lilas>'], true));
    }

    public function testContactEmailKeepsTheVisitorAsReplyToAndTheOriginalMessage(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function (Email $email): bool {
            self::assertSame('CONTACT JARDIN SONORE - Demande de devis - Claire Martin', $email->getSubject());
            self::assertSame('contact@jardin-sonore.test', $email->getTo()[0]->getAddress());
            self::assertSame('claire@example.test', $email->getReplyTo()[0]->getAddress());
            self::assertStringContainsString('Structure: Crèche des Lilas', (string) $email->getTextBody());
            self::assertStringContainsString("Message:\nBonjour, je souhaite quatre séances.", (string) $email->getTextBody());

            return true;
        }));
        $requestEntity = new CommercialRequestEntity('site', 'Claire Martin', 'claire@example.test', 'Bonjour, je souhaite quatre séances.', new DateTimeImmutable('2026-10-09'), 'Crèche des Lilas');

        (new SymfonyCommercialContactSender($mailer, $this->twig, $this->translator, 'contact@jardin-sonore.test'))->send($requestEntity);
    }

    public function testSingleOrganizationNamesAreHiddenInBothFormats(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function (Email $email): bool {
            self::assertStringNotContainsString('Structure confidentielle', (string) $email->getHtmlBody());
            self::assertStringNotContainsString('Structure confidentielle', (string) $email->getTextBody());
            self::assertStringContainsString('Bonjour,', (string) $email->getHtmlBody());

            return true;
        }));
        (new SymfonySessionNotificationMailSender($mailer, $this->twig, $this->translator, 'https://jardin-sonore.test', 'bonjour@jardin-sonore.test', 'Jardin Sonore'))
            ->send(new SessionNotificationMailView('client@portal.test', null, 'Sons', new DateTimeImmutable('2026-09-30'), 'les-sons', ['Structure confidentielle'], false));
    }

    public function testInvitationAndResetHavePlainTextAndNoRedundantHeadings(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->with(self::callback(static function (Email $email): bool {
            $html = (string) $email->getHtmlBody();
            self::assertNotEmpty($email->getTextBody());
            self::assertStringContainsString('https://jardin-sonore.test/portail/definir-mot-de-passe/token-fictif', $html);
            self::assertStringContainsString('https://jardin-sonore.test/portail/definir-mot-de-passe/token-fictif', (string) $email->getTextBody());
            self::assertStringNotContainsString('<h1', $html);
            self::assertStringNotContainsString('prochainement', $html);
            self::assertStringContainsString('Bonjour Camille,', $html);

            return true;
        }));
        $sender = new SymfonyPortalAccountMailSender($mailer, $this->twig, $this->translator, 'https://jardin-sonore.test', 'bonjour@jardin-sonore.test', 'Jardin Sonore');
        $user = (new UserEntity())->setEmail('client@portal.test')->setFirstName('Camille');
        $sender->sendInvitation($user, 'token-fictif');
        $sender->sendPasswordReset($user, 'token-fictif');
    }
}
