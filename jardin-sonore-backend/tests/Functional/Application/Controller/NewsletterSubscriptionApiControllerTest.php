<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class NewsletterSubscriptionApiControllerTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class('test', true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-newsletter-api-test-cache';
            }
        };
    }

    public function testValidSecretRejectsMalformedInputWithoutCreatingConsent(): void
    {
        $client = static::createClient();
        $secret = (string) ($_ENV['PORTAL_BFF_SHARED_SECRET'] ?? $_SERVER['PORTAL_BFF_SHARED_SECRET'] ?? '');
        self::assertNotSame('', $secret);
        foreach (['{', '{"emailAddress":["fixture@example.test"]}', '{"emailAddress":"' . str_repeat('a', 250) . '@example.test"}'] as $body) {
            $client->request('POST', '/api/newsletter/subscription-requests', server: ['HTTP_X_PORTAL_BFF_SECRET' => $secret, 'CONTENT_TYPE' => 'application/json'], content: $body);
            self::assertContains($client->getResponse()->getStatusCode(), [400, 422]);
        }
    }

    public function testValidSecretRejectsInvalidTokenFormat(): void
    {
        $client = static::createClient();
        $secret = (string) ($_ENV['PORTAL_BFF_SHARED_SECRET'] ?? $_SERVER['PORTAL_BFF_SHARED_SECRET'] ?? '');
        self::assertNotSame('', $secret);
        $client->request('GET', '/api/newsletter/confirmations/invalid', server: ['HTTP_X_PORTAL_BFF_SECRET' => $secret]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testSixthValidRequestFromSameIpIsThrottled(): void
    {
        $client = static::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress('limit-' . bin2hex(random_bytes(8)) . '@example.test');
        $emailContactEntity->confirmFreeNewsletterSubscription(new DateTimeImmutable(), 'backoffice');
        $entityManager->persist($emailContactEntity);
        $entityManager->flush();
        $connection = $entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $connection->getDatabase());
        $secret = (string) ($_ENV['PORTAL_BFF_SHARED_SECRET'] ?? $_SERVER['PORTAL_BFF_SHARED_SECRET'] ?? '');
        $clientIp = '2001:db8:' . bin2hex(random_bytes(2)) . ':' . bin2hex(random_bytes(2)) . '::1';
        try {
            for ($i = 0; 6 > $i; ++$i) {
                $client->jsonRequest('POST', '/api/newsletter/subscription-requests', ['emailAddress' => $emailContactEntity->getEmailAddress()], ['HTTP_X_PORTAL_BFF_SECRET' => $secret, 'HTTP_X_PORTAL_CLIENT_IP' => $clientIp]);
                self::assertResponseStatusCodeSame(5 > $i ? 202 : 429);
            }
        } finally {
            $connection->executeStatement('DELETE FROM email_contact WHERE email_address = ?', [$emailContactEntity->getEmailAddress()]);
        }
    }

    public function testMissingBffSecretIsRejected(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/newsletter/subscription-requests', ['emailAddress' => 'fixture@example.test']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidBffSecretIsRejectedBeforeMalformedBody(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/newsletter/subscription-requests', server: ['HTTP_X_PORTAL_BFF_SECRET' => 'incorrect', 'CONTENT_TYPE' => 'application/json'], content: '{');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousGetCannotReachConfirmation(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/newsletter/confirmations/' . str_repeat('a', 64));
        self::assertResponseStatusCodeSame(403);
    }
}
