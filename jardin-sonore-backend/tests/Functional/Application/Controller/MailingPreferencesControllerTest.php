<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MailingPreferencesControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testGetIsNeutralAndPostCanRefuseOnlyTheNewsletter(): void
    {
        $client = self::createClient(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $contact = (new EmailContactEntity())->setEmailAddress('preferences-http-' . bin2hex(random_bytes(6)) . '@example.test')->setDirectoryMailingAllowed(true);
        $entityManager->persist($contact);
        $entityManager->flush();
        $uuid = $contact->getUuid();
        $url = '/mailing/preferences/' . $contact->getUnsubscribeToken();
        try {
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertFalse($contact->isNewsletterRefused());
            $client->request('POST', $url, ['action' => 'all']);
            self::assertResponseStatusCodeSame(403);
            $crawler = $client->request('GET', $url);
            $client->submit($crawler->selectButton('Ne plus recevoir la newsletter')->form());
            self::assertResponseRedirects();
            $updated = self::getContainer()->get(EntityManagerInterface::class)->getRepository(EmailContactEntity::class)->findOneBy(['uuid' => $uuid]);
            self::assertTrue($updated->isNewsletterRefused());
            self::assertFalse($updated->isOutreachRefused());
        } finally {
            self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('DELETE FROM email_contact WHERE uuid = ?', [$uuid->toBinary()]);
        }
    }

    public function testUnknownTokenDoesNotExposePreferences(): void
    {
        $client = self::createClient(['environment' => 'test']);
        $client->request('GET', '/mailing/preferences/not-a-token');
        self::assertResponseStatusCodeSame(404);
    }
}
