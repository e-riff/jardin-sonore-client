<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Entity;

use App\Domain\Model\AddressBook\AddressContactType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use PHPUnit\Framework\TestCase;

final class AddressContactEntityTest extends TestCase
{
    public function testClearingAnOptionalTypeKeepsTheDefaultAddressType(): void
    {
        $addressContactEntity = new AddressContactEntity();

        $addressContactEntity->setType(null);

        self::assertSame(AddressContactType::MAIN, $addressContactEntity->getType());
    }
}
