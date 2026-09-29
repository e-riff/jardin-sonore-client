<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use PHPUnit\Framework\TestCase;

final class PhoneContactLinkEntityTest extends TestCase
{
    public function testRebindingPreservesOtherLinksAndUpdatesBothContactCollections(): void
    {
        $originalPhoneContactEntity = new PhoneContactEntity();
        $targetPhoneContactEntity = new PhoneContactEntity();
        $phoneContactLinkEntity = (new PhoneContactLinkEntity())->setPhoneContact($originalPhoneContactEntity);
        $otherPhoneContactLinkEntity = (new PhoneContactLinkEntity())->setPhoneContact($originalPhoneContactEntity);

        $phoneContactLinkEntity->setPhoneContact($targetPhoneContactEntity);

        self::assertFalse($originalPhoneContactEntity->getPhoneContactLinks()->contains($phoneContactLinkEntity));
        self::assertTrue($originalPhoneContactEntity->getPhoneContactLinks()->contains($otherPhoneContactLinkEntity));
        self::assertSame($originalPhoneContactEntity, $otherPhoneContactLinkEntity->getPhoneContact());
        self::assertTrue($targetPhoneContactEntity->getPhoneContactLinks()->contains($phoneContactLinkEntity));
        self::assertSame($targetPhoneContactEntity, $phoneContactLinkEntity->getPhoneContact());

        $phoneContactLinkEntity->setPhoneContact(null);
        self::assertCount(0, $targetPhoneContactEntity->getPhoneContactLinks());
        self::assertNull($phoneContactLinkEntity->getPhoneContact());
    }
}
