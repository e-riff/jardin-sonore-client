<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialContactApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;
    private string $secret;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true);
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
        $this->secret = (string) ($_ENV['PORTAL_BFF_SHARED_SECRET'] ?? $_SERVER['PORTAL_BFF_SHARED_SECRET'] ?? '');
        self::assertNotSame('', $this->secret);
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testMissingSecretIsRejectedBeforeBodyParsing(): void
    {
        $this->client->request('POST', '/api/commercial/contact-requests', server: ['CONTENT_TYPE' => 'application/json'], content: '{');

        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidPayloadIsRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/commercial/contact-requests', ['name' => 'Claire Martin', 'emailAddress' => 'bad', 'message' => '', 'submissionKey' => 'bad'], ['HTTP_X_PORTAL_BFF_SECRET' => $this->secret]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testValidReplayReturnsSameRequestAndChangedContentConflicts(): void
    {
        $submissionKey = sprintf('a3f21d91-5287-4f63-a8ea-%012x', random_int(0, 0xFFFFFFFFFFFF));
        $payload = ['name' => 'Claire Martin', 'emailAddress' => 'claire@example.test', 'message' => 'Bonjour', 'submissionKey' => $submissionKey];
        $server = ['HTTP_X_PORTAL_BFF_SECRET' => $this->secret];

        $this->client->jsonRequest('POST', '/api/commercial/contact-requests', $payload, $server);
        self::assertResponseStatusCodeSame(202);
        $firstResponse = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsInt($firstResponse['requestId'] ?? null);

        $this->client->jsonRequest('POST', '/api/commercial/contact-requests', $payload, $server);
        self::assertResponseStatusCodeSame(202);
        $secondResponse = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($firstResponse['requestId'], $secondResponse['requestId']);

        $this->client->jsonRequest('POST', '/api/commercial/contact-requests', [...$payload, 'message' => 'Autre demande'], $server);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_request WHERE submission_key = ?', [$submissionKey]));
    }
}
