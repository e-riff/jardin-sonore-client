<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CommercialContactRequestInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class CommercialContactRequestInputTest extends TestCase
{
    public function testValidInputHasStableFingerprintAfterTrimming(): void
    {
        $input = new CommercialContactRequestInput('  Claire Martin ', ' Claire@example.test ', '  Bonjour ', 'a3f21d91-5287-4f63-a8ea-19f63a887e4b', '  Crèche ', ' Mornant ', ' 06 11 22 33 44 ');
        $sameInput = new CommercialContactRequestInput('Claire Martin', 'claire@example.test', 'Bonjour', 'a3f21d91-5287-4f63-a8ea-19f63a887e4b', 'Crèche', 'Mornant', '06 11 22 33 44');

        self::assertSame($sameInput->fingerprint(), $input->fingerprint());
        self::assertSame('Claire Martin', $input->name);
        self::assertSame('claire@example.test', $input->emailAddress);
        self::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($input));
    }

    public function testChangedMessageWithSameKeyHasDifferentFingerprint(): void
    {
        $first = new CommercialContactRequestInput('Claire Martin', 'claire@example.test', 'Bonjour', 'a3f21d91-5287-4f63-a8ea-19f63a887e4b');
        $second = new CommercialContactRequestInput('Claire Martin', 'claire@example.test', 'Autre demande', 'a3f21d91-5287-4f63-a8ea-19f63a887e4b');

        self::assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    public function testMissingMessageAndMalformedSubmissionKeyAreRejected(): void
    {
        $input = new CommercialContactRequestInput('Claire Martin', 'claire@example.test', ' ', 'invalid');

        self::assertGreaterThanOrEqual(2, count(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($input)));
    }
}
