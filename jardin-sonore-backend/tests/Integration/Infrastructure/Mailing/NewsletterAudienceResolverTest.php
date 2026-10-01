<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Entity\DirectoryEntryEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use App\Infrastructure\Doctrine\Entity\TagEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Mailing\DoctrineNewsletterAudienceResolver;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NewsletterAudienceResolverTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
        // Isolate the audience from existing fixtures; rollback restores their consent.
        $this->connection->executeStatement('UPDATE email_contact SET opt_in_newsletter = 0');
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    public function testDirectorySourceKeepsOrganizationAndPersonRecipients(): void
    {
        $organizationEntity = $this->organization();
        $this->link($organizationEntity, $this->email('organization'));
        $personEntity = (new PersonEntity())->setFirstName('Alice')->setLastName('Test')->setOrganization($organizationEntity);
        $this->entityManager->persist($personEntity);
        $this->link($personEntity, $this->email('person'));

        self::assertSame(['organization@example.test', 'person@example.test'], $this->addresses());
    }

    public function testPortalSourceDoesNotRequireAnOrganizationEmail(): void
    {
        $organizationEntity = $this->organization();
        $secondOrganizationEntity = $this->organization();
        $emailContactEntity = $this->email('portal');
        $this->user('portal', [$organizationEntity, $secondOrganizationEntity]);

        $resolution = $this->resolver()->resolve(NewsletterAudienceFilter::empty());
        self::assertSame(1, $resolution->getTotal());
        self::assertSame(['portal@example.test'], $this->addresses());
        self::assertSame($emailContactEntity->getUnsubscribeToken(), $resolution->getRecipients()[0]->getUnsubscribeToken());
        self::assertSame('Alice Test', $resolution->getRecipients()[0]->getDisplayName());
    }

    public function testFreeSourceRequiresExplicitOptionAndConfirmation(): void
    {
        $this->email('confirmed', free: true);
        $this->email('historical');
        $this->email('unconfirmed')->setFreeNewsletterSubscription(true);
        $this->email('date-only')->setFreeNewsletterSubscriptionConfirmedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        self::assertSame([], $this->addresses());
        self::assertSame(['confirmed@example.test'], $this->addresses(new NewsletterAudienceFilter(includeFreeSubscribers: true)));
    }

    public function testUnionDeduplicatesBeforeLimitAndPreservesPlusAndDotVariants(): void
    {
        $organizationEntity = $this->organization();
        $sharedEmailContactEntity = $this->email('shared', free: true);
        $this->link($organizationEntity, $sharedEmailContactEntity);
        $this->user('shared', [$organizationEntity, $this->organization()]);
        $this->email('shared+news', free: true);
        $this->email('sha.red', free: true);
        $this->email('directory');
        $this->link($organizationEntity, $this->email('directory-only'));
        $this->email('portal-only');
        $this->user('portal-only', [$organizationEntity]);
        $this->email('free-only', free: true);
        $newsletterAudienceFilter = new NewsletterAudienceFilter(includeFreeSubscribers: true);

        self::assertSame([
            'directory-only@example.test', 'free-only@example.test', 'portal-only@example.test',
            'sha.red@example.test', 'shared+news@example.test', 'shared@example.test',
        ], $this->addresses($newsletterAudienceFilter));
        $resolution = $this->resolver()->resolve($newsletterAudienceFilter, 2);
        self::assertSame(6, $resolution->getTotal());
        self::assertCount(2, $resolution->getRecipients());
        self::assertTrue($resolution->isTruncated());
    }

    public function testCaseAndSurroundingSpacesMatchPortalConsentAndDeduplicate(): void
    {
        $organizationEntity = $this->organization();
        $emailContactEntity = $this->email('shared', free: true);
        $this->link($organizationEntity, $emailContactEntity);
        $userEntity = $this->user('shared', [$organizationEntity]);
        $this->connection->update('email_contact', ['email_address' => ' Shared@Example.Test '], ['id' => $emailContactEntity->getId()]);
        $this->connection->update('portal_user', ['email' => ' SHARED@example.test '], ['id' => $userEntity->getId()]);

        self::assertSame(['shared@example.test'], $this->addresses());
        self::assertSame(['shared@example.test'], $this->addresses(new NewsletterAudienceFilter(includeFreeSubscribers: true)));
        $this->connection->delete('contact_details_email_link', ['email_contact_id' => $emailContactEntity->getId()]);
        self::assertSame(['shared@example.test'], $this->addresses());
    }

    #[DataProvider('invalidPortalStates')]
    public function testPortalSourceExcludesUnusableAccountsAndAccesses(string $state): void
    {
        $organizationEntity = $this->organization();
        $this->email('portal');
        $userEntity = $this->user('portal', [$organizationEntity]);
        match ($state) {
            'inactive-account' => $userEntity->setActive(false),
            'pending' => $userEntity->setStatus(UserStatus::PENDING),
            'inactive-status' => $userEntity->setStatus(UserStatus::INACTIVE),
            'null-password' => $userEntity->setPassword(null),
            'empty-password' => $userEntity->setPassword(''),
            'blank-password' => $userEntity->setPassword('   '),
            'revoked-access' => $userEntity->getOrganizationAccesses()->first()->setActive(false),
            'inactive-organization' => $organizationEntity->setActive(false),
        };
        $this->entityManager->flush();

        self::assertSame([], $this->addresses(new NewsletterAudienceFilter(organizationUuids: [$organizationEntity->getUuid()->toRfc4122()])));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPortalStates(): iterable
    {
        foreach (['inactive-account', 'pending', 'inactive-status', 'null-password', 'empty-password', 'blank-password', 'revoked-access', 'inactive-organization'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('invalidContactStates')]
    public function testAllSourcesRespectCurrentContactEligibility(string $state): void
    {
        $organizationEntity = $this->organization();
        $emailContactEntity = $this->email('shared', free: true);
        $this->link($organizationEntity, $emailContactEntity);
        $this->user('shared', [$organizationEntity]);
        match ($state) {
            'opt-out' => $emailContactEntity->setOptInNewsletter(false),
            'withdrawn' => $emailContactEntity->setUnsubscribedAt(new DateTimeImmutable()),
            'inactive' => $emailContactEntity->setActive(false),
        };
        $this->entityManager->flush();

        self::assertSame([], $this->addresses(new NewsletterAudienceFilter(
            organizationUuids: [$organizationEntity->getUuid()->toRfc4122()], includeFreeSubscribers: true,
        )));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidContactStates(): iterable
    {
        yield 'opt-out' => ['opt-out'];
        yield 'withdrawn' => ['withdrawn'];
        yield 'inactive' => ['inactive'];
    }

    public function testUnknownPortalAddressIsNotSubscribedOrCreatedByResolution(): void
    {
        $this->user('unknown', [$this->organization()]);
        $countBefore = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM email_contact');

        self::assertSame([], $this->addresses(new NewsletterAudienceFilter(includeFreeSubscribers: true)));
        self::assertSame($countBefore, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM email_contact'));
    }

    public function testOrganizationCriteriaApplyToDirectoryAndPortalWithExplicitUnion(): void
    {
        $matchingOrganizationEntity = $this->organization();
        $tagEntity = (new TagEntity())->setLabel('Audience ' . bin2hex(random_bytes(8)));
        $this->entityManager->persist($tagEntity);
        $matchingOrganizationEntity->addTag($tagEntity);
        $this->link($matchingOrganizationEntity, $this->email('directory'));
        $this->email('matching');
        $this->user('matching', [$matchingOrganizationEntity]);
        $otherOrganizationEntity = $this->organization()->setType(OrganizationType::MAIRIE)->setSector(OrganizationSector::PRIVATE)->setCustomerStatus(CustomerStatus::PROSPECT);
        $this->email('explicit');
        $this->user('explicit', [$otherOrganizationEntity]);
        $this->entityManager->flush();

        $criteria = [
            new NewsletterAudienceFilter(organizationTypes: [OrganizationType::CRECHE]),
            new NewsletterAudienceFilter(organizationSectors: [OrganizationSector::PUBLIC]),
            new NewsletterAudienceFilter(customerStatuses: [CustomerStatus::CUSTOMER]),
            new NewsletterAudienceFilter(tagUuids: [$tagEntity->getUuid()->toRfc4122()]),
        ];
        foreach ($criteria as $newsletterAudienceFilter) {
            self::assertSame(['directory@example.test', 'matching@example.test'], $this->addresses($newsletterAudienceFilter));
        }
        self::assertSame(['directory@example.test', 'explicit@example.test', 'matching@example.test'], $this->addresses(new NewsletterAudienceFilter(
            organizationTypes: [OrganizationType::CRECHE], organizationSectors: [OrganizationSector::PUBLIC],
            customerStatuses: [CustomerStatus::CUSTOMER], tagUuids: [$tagEntity->getUuid()->toRfc4122()],
            organizationUuids: [$otherOrganizationEntity->getUuid()->toRfc4122()],
        )));
    }

    public function testGeographyUsesStructureAddressAndExplicitUnionWhileFreeSourceIgnoresZone(): void
    {
        $nearMunicipalityEntity = $this->municipality('near', 48.85, 2.35);
        $farMunicipalityEntity = $this->municipality('far', 43.60, 1.44);
        $nearOrganizationEntity = $this->organization();
        $farOrganizationEntity = $this->organization();
        $noAddressOrganizationEntity = $this->organization();
        $this->address($nearOrganizationEntity, $nearMunicipalityEntity);
        $this->address($farOrganizationEntity, $farMunicipalityEntity);
        $this->link($nearOrganizationEntity, $this->email('near-directory'));
        $this->link($farOrganizationEntity, $this->email('far-directory'));
        $this->email('near');
        $this->user('near', [$nearOrganizationEntity]);
        $this->email('far');
        $this->user('far', [$farOrganizationEntity]);
        $this->email('no-address');
        $this->user('no-address', [$noAddressOrganizationEntity]);
        $this->email('free', free: true);
        foreach ([
            new NewsletterAudienceFilter(municipalityInseeCodes: [$nearMunicipalityEntity->getInseeCode()]),
            new NewsletterAudienceFilter(departmentCodes: [$nearMunicipalityEntity->getDepartment()->getCode()]),
            new NewsletterAudienceFilter(regionCodes: [$nearMunicipalityEntity->getDepartment()->getRegion()->getCode()]),
            new NewsletterAudienceFilter(radiusKilometers: 10, radiusOrigin: NewsletterAudienceRadiusOrigin::CUSTOM, radiusOriginCustomLatitude: 48.85, radiusOriginCustomLongitude: 2.35),
            new NewsletterAudienceFilter(radiusKilometers: 10, radiusOrigin: NewsletterAudienceRadiusOrigin::MUNICIPALITY, radiusOriginMunicipalityInseeCode: $nearMunicipalityEntity->getInseeCode()),
        ] as $newsletterAudienceFilter) {
            self::assertSame(['near-directory@example.test', 'near@example.test'], $this->addresses($newsletterAudienceFilter));
        }
        self::assertSame(['far-directory@example.test', 'far@example.test', 'free@example.test', 'near-directory@example.test', 'near@example.test', 'no-address@example.test'], $this->addresses(new NewsletterAudienceFilter(
            municipalityInseeCodes: [$nearMunicipalityEntity->getInseeCode()],
            organizationUuids: [$farOrganizationEntity->getUuid()->toRfc4122(), $noAddressOrganizationEntity->getUuid()->toRfc4122()],
            includeFreeSubscribers: true,
        )));
        self::assertSame(['far-directory@example.test', 'far@example.test', 'free@example.test', 'near-directory@example.test', 'near@example.test', 'no-address@example.test'], $this->addresses(new NewsletterAudienceFilter(
            radiusKilometers: 10, radiusOrigin: NewsletterAudienceRadiusOrigin::CUSTOM, radiusOriginCustomLatitude: 48.85, radiusOriginCustomLongitude: 2.35,
            organizationUuids: [$farOrganizationEntity->getUuid()->toRfc4122(), $noAddressOrganizationEntity->getUuid()->toRfc4122()], includeFreeSubscribers: true,
        )));
    }

    public function testFreeMembershipSurvivesLinkingToAnOutOfZoneStructure(): void
    {
        $emailContactEntity = $this->email('free', free: true);
        $this->link($this->organization(), $emailContactEntity);

        self::assertSame(['free@example.test'], $this->addresses(new NewsletterAudienceFilter(municipalityInseeCodes: ['absent'], includeFreeSubscribers: true)));
        self::assertSame([], $this->addresses(new NewsletterAudienceFilter(municipalityInseeCodes: ['absent'])));
        self::assertSame(['free@example.test'], $this->addresses(new NewsletterAudienceFilter(
            organizationTypes: [OrganizationType::MAIRIE], customerStatuses: [CustomerStatus::PROSPECT], includeFreeSubscribers: true,
        )));
    }

    public function testPersonKeepsInheritedStructureCriteriaAndInactiveLinksAreExcluded(): void
    {
        $organizationEntity = $this->organization();
        $municipalityEntity = $this->municipality('near', 48.85, 2.35);
        $this->address($organizationEntity, $municipalityEntity);
        $tagEntity = (new TagEntity())->setLabel('Person audience ' . bin2hex(random_bytes(8)));
        $this->entityManager->persist($tagEntity);
        $organizationEntity->addTag($tagEntity);
        $personEntity = (new PersonEntity())->setFirstName('Alice')->setLastName('Test')->setOrganization($organizationEntity)
            ->setCustomerStatus(CustomerStatus::PROSPECT);
        $this->entityManager->persist($personEntity);
        $emailContactEntity = $this->email('person');
        $this->link($personEntity, $emailContactEntity);
        $newsletterAudienceFilter = new NewsletterAudienceFilter(
            organizationTypes: [OrganizationType::CRECHE], organizationSectors: [OrganizationSector::PUBLIC],
            customerStatuses: [CustomerStatus::CUSTOMER], tagUuids: [$tagEntity->getUuid()->toRfc4122()],
            municipalityInseeCodes: [$municipalityEntity->getInseeCode()],
        );

        self::assertSame(['person@example.test'], $this->addresses($newsletterAudienceFilter));
        $emailContactEntity->getEmailContactLinks()->first()->setActive(false);
        $this->entityManager->flush();
        self::assertSame([], $this->addresses($newsletterAudienceFilter));
        $emailContactEntity->getEmailContactLinks()->first()->setActive(true);
        $organizationEntity->setActive(false);
        $this->entityManager->flush();
        self::assertSame([], $this->addresses($newsletterAudienceFilter));
    }

    private function resolver(): DoctrineNewsletterAudienceResolver
    {
        return new DoctrineNewsletterAudienceResolver($this->connection, '48.85', '2.35');
    }

    /** @return list<string> */
    private function addresses(?NewsletterAudienceFilter $newsletterAudienceFilter = null): array
    {
        $addresses = array_map(
            static fn (NewsletterRecipient $newsletterRecipient): string => $newsletterRecipient->getEmailAddress()->value(),
            $this->resolver()->resolve($newsletterAudienceFilter ?? NewsletterAudienceFilter::empty())->getRecipients(),
        );
        sort($addresses);

        return $addresses;
    }

    private function organization(): OrganizationEntity
    {
        $organizationEntity = (new OrganizationEntity())->setName('Audience ' . bin2hex(random_bytes(8)))
            ->setType(OrganizationType::CRECHE)->setSector(OrganizationSector::PUBLIC)->setCustomerStatus(CustomerStatus::CUSTOMER);
        $this->entityManager->persist($organizationEntity);
        $this->entityManager->flush();

        return $organizationEntity;
    }

    private function email(string $localPart, bool $free = false): EmailContactEntity
    {
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress("{$localPart}@example.test");
        if ($free) {
            $emailContactEntity->confirmFreeNewsletterSubscription(new DateTimeImmutable(), 'test');
        }
        $this->entityManager->persist($emailContactEntity);
        $this->entityManager->flush();

        return $emailContactEntity;
    }

    private function link(DirectoryEntryEntity $directoryEntryEntity, EmailContactEntity $emailContactEntity): void
    {
        $directoryEntryEntity->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailContact($emailContactEntity));
        $this->entityManager->flush();
    }

    /** @param list<OrganizationEntity> $organizationEntities */
    private function user(string $localPart, array $organizationEntities): UserEntity
    {
        $userEntity = (new UserEntity())->setEmail("{$localPart}@example.test")->setFirstName('Alice')->setLastName('Test')
            ->setStatus(UserStatus::ACTIVE)->setPassword('unused');
        foreach ($organizationEntities as $organizationEntity) {
            $userEntity->addOrganizationAccess((new UserOrganizationAccessEntity())->setOrganization($organizationEntity));
        }
        $this->entityManager->persist($userEntity);
        $this->entityManager->flush();

        return $userEntity;
    }

    private function municipality(string $label, float $latitude, float $longitude): MunicipalityEntity
    {
        $code = 'near' === $label ? 'XN' : 'XF';
        $regionEntity = (new RegionEntity())->setName("Region {$label}")->setCode($code);
        $departmentEntity = (new DepartmentEntity())->setName("Department {$label}")->setCode($code)->setRegion($regionEntity);
        $municipalityEntity = (new MunicipalityEntity())->setName("Municipality {$label}")->setInseeCode($code)
            ->setDepartment($departmentEntity)->setCenterLatitude($latitude)->setCenterLongitude($longitude);
        foreach ([$regionEntity, $departmentEntity, $municipalityEntity] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return $municipalityEntity;
    }

    private function address(OrganizationEntity $organizationEntity, MunicipalityEntity $municipalityEntity): void
    {
        $organizationEntity->getContactDetails()->addAddressContact((new AddressContactEntity())->setMunicipality($municipalityEntity));
        $this->entityManager->flush();
    }
}
