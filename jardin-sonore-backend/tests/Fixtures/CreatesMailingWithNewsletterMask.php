<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Application\Mailing\CreateMailingCampaign;
use App\Application\Mailing\CreateMailingCampaignInput;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Infrastructure\Doctrine\Entity\MailingAudienceMaskEntity;
use Closure;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesMailingWithNewsletterMask
{
    private function configuredCampaignCreator(): Closure
    {
        return static function (CreateMailingCampaignInput $input): MailingCampaign {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $existing = $entityManager->getRepository(MailingAudienceMaskEntity::class)->findOneBy(['reservedRole' => 'newsletter']);
            $mask = $existing ?? (new MailingAudienceMaskEntity())->setName('Fixture newsletter')->setReservedRole('newsletter')->setAudienceFilter(['departmentCodes' => ['42']]);
            if (null === $existing) {
                $entityManager->persist($mask);
                $entityManager->flush();
            }
            try {
                return (static::getContainer()->get(CreateMailingCampaign::class))($input);
            } finally {
                if (null === $existing) {
                    $entityManager->remove($mask);
                    $entityManager->flush();
                }
            }
        };
    }
}
