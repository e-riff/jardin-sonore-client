<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use PHPUnit\Framework\TestCase;

final class EmailContactLinkEntityTest extends TestCase
{
    public function testReplacingTheSharedEmailContactDetachesTheLinkFromThePreviousContact(): void
    {
        $previousEmailContact = (new EmailContactEntity())->setEmailAddress('previous@example.test');
        $existingEmailContact = (new EmailContactEntity())->setEmailAddress('existing@example.test');
        $emailContactLink = (new EmailContactLinkEntity())->setEmailContact($previousEmailContact);

        $emailContactLink->setEmailContact($existingEmailContact);

        self::assertTrue($previousEmailContact->getEmailContactLinks()->isEmpty());
        self::assertTrue($existingEmailContact->getEmailContactLinks()->contains($emailContactLink));
        self::assertSame($existingEmailContact, $emailContactLink->getEmailContact());
    }
}
