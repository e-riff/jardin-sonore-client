<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model\Portal;

use App\Domain\Model\Portal\PortalPasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PortalPasswordPolicyTest extends TestCase
{
    #[DataProvider('passwordExamples')]
    public function testItChecksThePortalPasswordRequirements(string $password, bool $isValid): void
    {
        self::assertSame($isValid, (new PortalPasswordPolicy())->isValid($password));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function passwordExamples(): iterable
    {
        yield 'valid password' => ['Passphrase longue 2026', true];
        yield 'exact minimum length with all character types' => ['Abcdefghij1K', true];
        yield 'too short' => ['Aa1', false];
        yield 'missing lowercase letter' => ['ABCDEFGHIJKL1', false];
        yield 'missing uppercase letter' => ['abcdefghijkl1', false];
        yield 'missing number' => ['Abcdefghijkl', false];
    }
}
