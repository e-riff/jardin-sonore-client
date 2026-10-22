<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DirectoryEstablishmentImportItemTest extends TestCase
{
    #[Test]
    public function itKeepsAdditionalContactsAndAddressesFromTheImportFile(): void
    {
        $item = DirectoryEstablishmentImportItem::fromArray([
            'id' => 'mjc-parmentier',
            'type' => 'MJC',
            'additionalEmails' => [' second@example.fr ', ''],
            'additionalPhones' => ['04 38 12 30 33'],
            'additionalAddresses' => ['17, rue Daguerre et Niépce, 38000 Grenoble'],
        ]);

        self::assertSame(['second@example.fr'], $item->additionalEmails);
        self::assertSame(['04 38 12 30 33'], $item->additionalPhones);
        self::assertSame(['17, rue Daguerre et Niépce, 38000 Grenoble'], $item->additionalAddresses);
    }
}
