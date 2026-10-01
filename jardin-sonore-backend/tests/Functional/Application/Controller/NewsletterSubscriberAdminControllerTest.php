<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;

final class NewsletterSubscriberAdminControllerTest extends WebTestCase
{
    private ?Connection $connection = null;
    private string $emailAddress = '';
    private string $adminEmailAddress = '';

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class('test', true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-newsletter-admin-test-cache';
            }
        };
    }

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->executeStatement('DELETE FROM email_contact WHERE email_address = ?', [$this->emailAddress]);
            $this->connection->executeStatement('DELETE FROM admin_user WHERE email = ?', [$this->adminEmailAddress]);
        }
        parent::tearDown();
    }

    public function testAnonymousCannotAddASubscriber(): void
    {
        static::createClient()->request('GET', '/newsletter/subscribers/new');
        self::assertResponseRedirects('/login');
    }

    public function testAdminCannotSubscribeWithoutAttestation(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/newsletter/subscribers/new');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('newsletter_subscriber[submit]')->form(['newsletter_subscriber[emailAddress]' => $this->emailAddress]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, (int) $this->connection?->fetchOne('SELECT COUNT(*) FROM email_contact WHERE email_address = ?', [$this->emailAddress]));
    }

    public function testAdminAttestationActivatesAndWithdrawalKeepsHistory(): void
    {
        $client = $this->adminClient();
        $crawler = $client->request('GET', '/newsletter/subscribers/new');
        $client->submit($crawler->selectButton('newsletter_subscriber[submit]')->form([
            'newsletter_subscriber[emailAddress]' => $this->emailAddress,
            'newsletter_subscriber[consentAttested]' => '1',
        ]));
        self::assertResponseRedirects();
        $row = $this->connection?->fetchAssociative('SELECT uuid, opt_in_newsletter, free_newsletter_subscription_origin FROM email_contact WHERE email_address = ?', [$this->emailAddress]);
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['opt_in_newsletter']);
        self::assertSame('backoffice', $row['free_newsletter_subscription_origin']);
        $client->followRedirect();
        $crawler = $client->request('GET', '/newsletter/subscribers/' . Uuid::fromBinary($row['uuid'])->toRfc4122() . '/edit');
        $client->submit($crawler->selectButton('newsletter-unsubscribe')->form());
        self::assertResponseRedirects();
        $history = $this->connection?->fetchAssociative('SELECT opt_in_newsletter, unsubscribed_at, free_newsletter_subscription_confirmed_at FROM email_contact WHERE email_address = ?', [$this->emailAddress]);
        self::assertIsArray($history);
        self::assertSame(0, (int) $history['opt_in_newsletter']);
        self::assertNotNull($history['unsubscribed_at']);
        self::assertNotNull($history['free_newsletter_subscription_confirmed_at']);
    }

    public function testInvalidCsrfCannotSubscribe(): void
    {
        $client = $this->adminClient();
        $client->request('POST', '/newsletter/subscribers/new', ['newsletter_subscriber' => ['emailAddress' => $this->emailAddress, 'consentAttested' => '1', '_token' => 'invalid']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, (int) $this->connection?->fetchOne('SELECT COUNT(*) FROM email_contact WHERE email_address = ?', [$this->emailAddress]));
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->emailAddress = 'admin-free-' . bin2hex(random_bytes(8)) . '@example.test';
        $this->adminEmailAddress = 'admin-' . bin2hex(random_bytes(8)) . '@example.test';
        $adminUserEntity = (new AdminUserEntity())->setEmail($this->adminEmailAddress)->setPassword('unused');
        $entityManager->persist($adminUserEntity);
        $entityManager->flush();
        $client->loginUser($adminUserEntity);

        return $client;
    }
}
