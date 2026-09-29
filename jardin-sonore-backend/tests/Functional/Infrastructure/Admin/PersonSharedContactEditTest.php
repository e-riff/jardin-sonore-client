<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Admin;

use App\Domain\Model\AddressBook\EmailContactType;
use App\Domain\Model\AddressBook\PhoneContactType;
use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class PersonSharedContactEditTest extends WebTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new class($options['environment'] ?? 'test', $options['debug'] ?? true) extends Kernel {
            public function getCacheDir(): string
            {
                return '/tmp/jardin-sonore-organization-people-cache';
            }
        };
    }

    /** @return iterable<string, array{string}> */
    public static function editScenarios(): iterable
    {
        yield 'new values' => ['new'];
        yield 'existing values' => ['existing'];
        yield 'link properties only' => ['local'];
        yield 'equivalent normalized values' => ['normalized'];
    }

    #[RunInSeparateProcess]
    #[DataProvider('editScenarios')]
    public function testEditingSharedContactsPreservesOtherPeople(string $scenario): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(8));
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress("shared-{$suffix}@example.test")
            ->setOptInNewsletter(true)->setUnsubscribedAt(new DateTimeImmutable('2025-01-02'))->setActive(false);
        $phoneContactEntity = (new PhoneContactEntity())->setPhoneNumber('+33' . random_int(100000000, 999999999))->setActive(false);
        $originalEmail = $emailContactEntity->getEmailAddress();
        $originalPhone = $phoneContactEntity->getPhoneNumber();
        $originalToken = $emailContactEntity->getUnsubscribeToken();
        $firstPersonEntity = $this->createPerson($emailContactEntity, $phoneContactEntity, 'Alice');
        $otherPersonEntity = $this->createPerson($emailContactEntity, $phoneContactEntity, 'Bob');
        $adminUserEntity = (new AdminUserEntity())->setEmail("admin-{$suffix}@example.test")->setPassword('unused');
        foreach ([$firstPersonEntity, $otherPersonEntity, $adminUserEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $targetEmailContactEntity = (new EmailContactEntity())->setEmailAddress("target-{$suffix}@example.test")->setOptInNewsletter(false);
        $targetPhoneContactEntity = (new PhoneContactEntity())->setPhoneNumber('+33' . random_int(100000000, 999999999));
        if ('existing' === $scenario) {
            $entityManager->persist($targetEmailContactEntity);
            $entityManager->persist($targetPhoneContactEntity);
        }
        $entityManager->flush();
        $emailCount = $entityManager->getRepository(EmailContactEntity::class)->count([]);
        $phoneCount = $entityManager->getRepository(PhoneContactEntity::class)->count([]);
        $originalEmailId = $emailContactEntity->getId();
        $originalPhoneId = $phoneContactEntity->getId();
        $firstPersonId = $firstPersonEntity->getId();
        $otherPersonId = $otherPersonEntity->getId();
        $targetEmailId = $targetEmailContactEntity->getId();
        $targetPhoneId = $targetPhoneContactEntity->getId();
        $client->loginUser($adminUserEntity);
        $crawler = $client->request('GET', "/backoffice/person/{$firstPersonId}/edit");
        self::assertResponseIsSuccessful();
        $form = $crawler->filterXPath('//form[.//input[contains(@name, "[emailAddress]")]]')->form();
        $formName = $crawler->filterXPath('//form[.//input[contains(@name, "[emailAddress]")]]')->attr('name');
        $emailPrefix = "{$formName}[contactDetails][emailContactLinks][0]";
        $phonePrefix = "{$formName}[contactDetails][phoneContactLinks][0]";
        $changesValue = in_array($scenario, ['new', 'existing'], true);
        $form["{$emailPrefix}[emailAddress]"] = $changesValue ? $targetEmailContactEntity->getEmailAddress() : ('normalized' === $scenario ? strtoupper($originalEmail) : $originalEmail);
        $form["{$phonePrefix}[phoneNumber]"] = $changesValue ? $targetPhoneContactEntity->getPhoneNumber() : ('normalized' === $scenario ? '00' . substr($originalPhone, 1) : $originalPhone);
        $form["{$emailPrefix}[label]"] = 'Local email';
        $form["{$phonePrefix}[label]"] = 'Local phone';
        $form["{$emailPrefix}[type]"] = '1';
        $form["{$phonePrefix}[type]"] = '1';
        $form["{$emailPrefix}[active]"]->untick();
        $form["{$phonePrefix}[active]"]->untick();
        $client->submit($form);
        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $firstPersonEntity = $entityManager->find(PersonEntity::class, $firstPersonId);
        $otherPersonEntity = $entityManager->find(PersonEntity::class, $otherPersonId);
        self::assertInstanceOf(PersonEntity::class, $firstPersonEntity);
        self::assertInstanceOf(PersonEntity::class, $otherPersonEntity);
        $otherEmailLink = $otherPersonEntity->getContactDetails()->getEmailContactLinks()->first();
        $otherPhoneLink = $otherPersonEntity->getContactDetails()->getPhoneContactLinks()->first();
        self::assertSame($originalEmail, $otherEmailLink->getEmailAddress());
        self::assertSame($originalPhone, $otherPhoneLink->getPhoneNumber());
        self::assertSame($originalEmailId, $otherEmailLink->getEmailContact()->getId());
        self::assertSame($originalPhoneId, $otherPhoneLink->getPhoneContact()->getId());
        self::assertSame('Original', $otherEmailLink->getLabel());
        self::assertSame('Original', $otherPhoneLink->getLabel());
        self::assertSame(EmailContactType::MAIN, $otherEmailLink->getType());
        self::assertSame(PhoneContactType::MAIN, $otherPhoneLink->getType());
        self::assertTrue($otherEmailLink->isActive());
        self::assertTrue($otherPhoneLink->isActive());
        self::assertTrue($otherEmailLink->getEmailContact()->hasOptInNewsletter());
        self::assertSame('2025-01-02', $otherEmailLink->getEmailContact()->getUnsubscribedAt()->format('Y-m-d'));
        self::assertSame($originalToken, $otherEmailLink->getEmailContact()->getUnsubscribeToken());
        self::assertFalse($otherEmailLink->getEmailContact()->isActive());
        self::assertFalse($otherPhoneLink->getPhoneContact()->isActive());
        $localEmailLink = $firstPersonEntity->getContactDetails()->getEmailContactLinks()->first();
        $localPhoneLink = $firstPersonEntity->getContactDetails()->getPhoneContactLinks()->first();
        self::assertSame('Local email', $localEmailLink->getLabel());
        self::assertSame('Local phone', $localPhoneLink->getLabel());
        self::assertSame(EmailContactType::WORK, $localEmailLink->getType());
        self::assertSame(PhoneContactType::MOBILE, $localPhoneLink->getType());
        self::assertFalse($localEmailLink->isActive());
        self::assertFalse($localPhoneLink->isActive());
        self::assertSame($changesValue ? $targetEmailContactEntity->getEmailAddress() : $originalEmail, $localEmailLink->getEmailAddress());
        self::assertSame($changesValue ? $targetPhoneContactEntity->getPhoneNumber() : $originalPhone, $localPhoneLink->getPhoneNumber());
        self::assertCount($changesValue ? 1 : 2, $otherEmailLink->getEmailContact()->getEmailContactLinks());
        self::assertCount($changesValue ? 1 : 2, $otherPhoneLink->getPhoneContact()->getPhoneContactLinks());
        if ($changesValue) {
            self::assertNotSame($originalEmailId, $localEmailLink->getEmailContact()->getId());
            self::assertNotSame($originalPhoneId, $localPhoneLink->getPhoneContact()->getId());
            self::assertFalse($localEmailLink->getEmailContact()->hasOptInNewsletter());
            self::assertNull($localEmailLink->getEmailContact()->getUnsubscribedAt());
            self::assertNotSame($originalToken, $localEmailLink->getEmailContact()->getUnsubscribeToken());
        } else {
            self::assertSame($originalEmailId, $localEmailLink->getEmailContact()->getId());
            self::assertSame($originalPhoneId, $localPhoneLink->getPhoneContact()->getId());
        }
        if ('existing' === $scenario) {
            self::assertSame($targetEmailId, $localEmailLink->getEmailContact()->getId());
            self::assertSame($targetPhoneId, $localPhoneLink->getPhoneContact()->getId());
        }
        self::assertSame($emailCount + ('new' === $scenario ? 1 : 0), $entityManager->getRepository(EmailContactEntity::class)->count([]));
        self::assertSame($phoneCount + ('new' === $scenario ? 1 : 0), $entityManager->getRepository(PhoneContactEntity::class)->count([]));
    }

    private function createPerson(EmailContactEntity $emailContactEntity, PhoneContactEntity $phoneContactEntity, string $firstName): PersonEntity
    {
        $personEntity = (new PersonEntity())->setFirstName($firstName)->setLastName('Shared');
        $personEntity->getContactDetails()
            ->addEmailContactLink((new EmailContactLinkEntity())->setEmailContact($emailContactEntity)->setLabel('Original'))
            ->addPhoneContactLink((new PhoneContactLinkEntity())->setPhoneContact($phoneContactEntity)->setLabel('Original'));

        return $personEntity;
    }
}
