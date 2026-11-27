<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MailingMaskProtectionTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testStaleOrdinaryMaskCannotDeleteAReservedMask(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->beginTransaction();
        try {
            $repository = self::getContainer()->get(MailingAudienceMaskRepositoryInterface::class);
            $mask = new MailingAudienceMask('Reserved test', new NewsletterAudienceFilter(departmentCodes: ['42']));
            $repository->save($mask);
            $staleMask = $repository->findByUuid($mask->getUuid());
            $mask->reserveForNewsletter();
            $repository->save($mask);
            $staleMask->update('Edited', new NewsletterAudienceFilter(departmentCodes: ['69']), []);
            $repository->save($staleMask);
            self::assertTrue($repository->findByUuid($mask->getUuid())->isNewsletterMask());
            $this->expectException(DomainException::class);
            $repository->remove($staleMask);
        } finally {
            $entityManager->getConnection()->rollBack();
        }
    }
}
