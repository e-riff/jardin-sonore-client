<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Mailing;

use App\Application\Form\Model\MailingAudienceFormModel;
use App\Application\Mailing\ApplyMailingAudienceMaskToCampaign;
use App\Application\Mailing\CreateMailingAudienceMask;
use App\Application\Mailing\CreateMailingAudienceMaskInput;
use App\Application\Mailing\ExtendMailingCampaignAudience;
use App\Application\Mailing\GetMailingAudienceMask;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\SendMailingCampaign;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Mailing\DoctrineMailingDeliveryQueue;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NewsletterAudienceCampaignFlowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertStringEndsWith('_test', (string) $this->entityManager->getConnection()->getDatabase());
        $this->entityManager->getConnection()->beginTransaction();
        $this->entityManager->getConnection()->executeStatement('UPDATE email_contact SET opt_in_newsletter = 0');
    }

    protected function tearDown(): void
    {
        while ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testSavedMaskAndCampaignPreserveFreeOptionAndPreviewEqualsQueue(): void
    {
        $this->addFreeContact('first');
        $this->addFreeContact('second');
        $formModel = new MailingAudienceFormModel();
        $formModel->includeFreeSubscribers = true;
        $formModel->municipalityInseeCodes = ['75056'];
        $mask = (self::getContainer()->get(CreateMailingAudienceMask::class))(new CreateMailingAudienceMaskInput('Test free audience', $formModel->toAudienceFilter(), ['75056']));
        $loadedMask = (self::getContainer()->get(GetMailingAudienceMask::class))($mask->getUuid());
        self::assertNotNull($loadedMask);
        self::assertTrue($loadedMask->getAudienceFilter()->includesFreeSubscribers());
        self::assertSame(['75056'], $loadedMask->getMaterializedMunicipalityInseeCodes());
        $campaign = $this->campaign();
        (self::getContainer()->get(ApplyMailingAudienceMaskToCampaign::class))($campaign, $loadedMask);
        $loadedCampaign = (self::getContainer()->get(GetMailingCampaign::class))($campaign->getUuid());
        self::assertNotNull($loadedCampaign);
        self::assertTrue($loadedCampaign->getAudienceFilter()->includesFreeSubscribers());
        self::assertSame(2, self::getContainer()->get(NewsletterAudienceResolverInterface::class)->resolve($loadedCampaign->getAudienceFilter())->getTotal());
        self::assertSame(2, (self::getContainer()->get(SendMailingCampaign::class))($loadedCampaign));
        self::assertCount(2, self::getContainer()->get(DoctrineMailingDeliveryQueue::class)->findCampaignRecipientEmailAddresses($campaign->getUuid()->toRfc4122()));
    }

    public function testExtensionOnlyAddsNewFreeAddresses(): void
    {
        $firstContact = $this->addFreeContact('existing');
        $filter = new NewsletterAudienceFilter(includeFreeSubscribers: true);
        $campaign = $this->campaign($filter);
        self::getContainer()->get(MailingCampaignRepositoryInterface::class)->save($campaign);
        (self::getContainer()->get(SendMailingCampaign::class))($campaign);
        $campaign->markDeliverySent();
        self::getContainer()->get(MailingCampaignRepositoryInterface::class)->save($campaign);
        $secondContact = $this->addFreeContact('new');
        $result = (self::getContainer()->get(ExtendMailingCampaignAudience::class))($campaign, $filter);
        self::assertSame(2, $result->matchedRecipientCount);
        self::assertSame(1, $result->alreadyLinkedRecipientCount);
        self::assertSame(1, $result->newRecipientCount);
        $addresses = self::getContainer()->get(DoctrineMailingDeliveryQueue::class)->findCampaignRecipientEmailAddresses($campaign->getUuid()->toRfc4122());
        self::assertCount(2, $addresses);
        self::assertContains($firstContact->getEmailAddress(), $addresses);
        self::assertContains($secondContact->getEmailAddress(), $addresses);
    }

    private function addFreeContact(string $name): EmailContactEntity
    {
        $emailContactEntity = (new EmailContactEntity())->setEmailAddress($name . '-' . bin2hex(random_bytes(5)) . '@example.test');
        $emailContactEntity->confirmFreeNewsletterSubscription(new DateTimeImmutable(), 'backoffice');
        $this->entityManager->persist($emailContactEntity);
        $this->entityManager->flush();

        return $emailContactEntity;
    }

    private function campaign(?NewsletterAudienceFilter $filter = null): MailingCampaign
    {
        return new MailingCampaign('Test campaign', 'Subject', 'Public title', 'Test text', 'default', $filter ?? NewsletterAudienceFilter::empty());
    }
}
