<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Mailing\MailingReportQuery;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class MailingReportControllerTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testReportDisplaysOnlyCampaignRecipientsAndTheirStatuses(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = (new AdminUserEntity())->setEmail('report-' . bin2hex(random_bytes(5)) . '@example.test')->setPassword('unused');
        $entityManager->persist($admin);
        $entityManager->flush();
        $client->loginUser($admin);
        $campaign = (static::getContainer()->get(CreateMailingCampaign::class))(new CreateMailingCampaignInput(
            internalTitle: 'Campagne bilan', emailSubject: 'Sujet', publicTitle: 'Titre', mainText: 'Contenu',
            subtitle: null, callToActionLabel: null, callToActionUrl: null, bannerImageFile: null,
        ));
        $connection = static::getContainer()->get(Connection::class);
        $emailAddress = 'bilan-' . bin2hex(random_bytes(5)) . '@example.test';
        $connection->insert('mailing_delivery_recipient', [
            'campaign_uuid' => $campaign->getUuid()->toRfc4122(), 'email_address' => $emailAddress,
            'unsubscribe_token' => bin2hex(random_bytes(24)), 'status' => 'sent',
            'queued_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00',
        ]);
        $organization = (new OrganizationEntity())->setName('Structure bilan')->setType(OrganizationType::CRECHE)->setSector(OrganizationSector::PUBLIC);
        $secondOrganization = (new OrganizationEntity())->setName('Structure bilan deuxième')->setType(OrganizationType::CRECHE)->setSector(OrganizationSector::PUBLIC);
        $entityManager->persist($secondOrganization);
        $email = (new EmailContactEntity())->setEmailAddress($emailAddress);
        $entityManager->persist($organization);
        $entityManager->persist($email);
        $entityManager->flush();
        $organization->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailContact($email));
        $secondOrganization->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailContact($email));
        $entityManager->flush();
        for ($index = 0; 27 > $index; ++$index) {
            $connection->insert('mailing_delivery_recipient', [
                'campaign_uuid' => $campaign->getUuid()->toRfc4122(), 'email_address' => sprintf('liste-%02d@example.test', $index),
                'unsubscribe_token' => bin2hex(random_bytes(24)), 'status' => 'failed',
                'queued_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00',
            ]);
        }
        $portalEmail = 'portail-' . bin2hex(random_bytes(5)) . '@example.test';
        $portalOrganization = (new OrganizationEntity())->setName('Structure portail bilan')->setType(OrganizationType::CRECHE)->setSector(OrganizationSector::PUBLIC);
        $portalUser = (new UserEntity())->setEmail($portalEmail)->setFirstName('Utilisateur')->setLastName('Portail')->setStatus(UserStatus::ACTIVE)->setPassword('unused');
        $portalUser->addOrganizationAccess((new UserOrganizationAccessEntity())->setOrganization($portalOrganization));
        $entityManager->persist($portalOrganization);
        $entityManager->persist($portalUser);
        $entityManager->flush();
        $connection->insert('mailing_delivery_recipient', [
            'campaign_uuid' => $campaign->getUuid()->toRfc4122(), 'email_address' => $portalEmail,
            'unsubscribe_token' => bin2hex(random_bytes(24)), 'status' => 'sent',
            'queued_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00',
        ]);
        $reportQuery = static::getContainer()->get(MailingReportQuery::class);
        self::assertSame(27, $reportQuery->search($campaign->getUuid()->toRfc4122(), 'emails', 'liste-', 1)['total']);
        self::assertCount(25, $reportQuery->search($campaign->getUuid()->toRfc4122(), 'emails', 'liste-', 1)['rows']);
        self::assertCount(2, $reportQuery->search($campaign->getUuid()->toRfc4122(), 'emails', 'liste-', 2)['rows']);
        $organizations = $reportQuery->search($campaign->getUuid()->toRfc4122(), 'organizations', 'Structure bilan', 1);
        self::assertSame(2, $organizations['total']);
        self::assertSame('Structure bilan', $organizations['rows'][0]['name']);
        self::assertSame(1, $reportQuery->search($campaign->getUuid()->toRfc4122(), 'organizations', 'Structure portail bilan', 1)['total']);
        self::assertSame(0, $reportQuery->search($campaign->getUuid()->toRfc4122(), 'emails', 'absent', 1)['total']);
        $client->request('GET', '/mailing/' . $campaign->getUuid()->toRfc4122() . '/bilan');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Campagne bilan', (string) $client->getResponse()->getContent());
        self::assertStringContainsString($emailAddress, (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Envoyés', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Structure bilan', (string) $client->getResponse()->getContent());
    }
}
