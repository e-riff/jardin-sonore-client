<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Domain\Model\Mailing\MailingCampaignType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\NewsletterSubscriptionRequestEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Mailing\MailingPreferenceManager;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class MailingPreferenceManagerTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testNewsletterConfirmationPreservesOutreachRefusalAndAdmission(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->beginTransaction();
        try {
            $emailContactEntity = (new EmailContactEntity())->setEmailAddress('preference-' . bin2hex(random_bytes(6)) . '@example.test');
            $entityManager->persist($emailContactEntity);
            $entityManager->flush();
            $manager = new MailingPreferenceManager($entityManager, self::getContainer()->get(EmailContactDoctrineRepository::class), new MockClock());
            $request = new NewsletterSubscriptionRequestEntity($emailContactEntity, hash('sha256', 'pending'), new DateTimeImmutable(), new DateTimeImmutable('+1 day'));
            $entityManager->persist($request);
            $entityManager->flush();
            $uuid = $emailContactEntity->getUuid()->toRfc4122();
            $manager->refuse($uuid, null);
            $manager->confirmNewsletter($uuid, 'backoffice');
            self::assertNotNull($entityManager->getConnection()->fetchOne('SELECT consumed_at FROM newsletter_subscription_request WHERE email_contact_id = ?', [$emailContactEntity->getId()]));
            self::assertFalse($emailContactEntity->isNewsletterRefused());
            self::assertTrue($emailContactEntity->isOutreachRefused());
            self::assertFalse($emailContactEntity->isDirectoryMailingAllowed());
            self::assertTrue($emailContactEntity->hasFreeNewsletterSubscription());
            $manager->allowDirectoryMailing($uuid);
            self::assertTrue($emailContactEntity->isOutreachRefused());
            $manager->allowOutreach($uuid);
            self::assertFalse($emailContactEntity->isOutreachRefused());
            $manager->refuse($uuid, MailingCampaignType::NEWSLETTER);
            self::assertFalse($emailContactEntity->isOutreachRefused());
            self::assertTrue($emailContactEntity->isNewsletterRefused());
        } finally {
            $entityManager->getConnection()->rollBack();
        }
    }
}
