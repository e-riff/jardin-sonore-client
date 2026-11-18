<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Form;

use App\Application\Form\Model\SessionSequenceFormModel;
use App\Application\Form\SessionSequenceType;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SessionSequenceTypeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testFormKeepsBlankLinesUsedToAlignLyricsAndGestures(): void
    {
        self::bootKernel(['environment' => 'test']);
        $form = self::getContainer()->get('form.factory')->create(SessionSequenceType::class, new SessionSequenceFormModel(), [
            'activity_only' => true, 'csrf_protection' => false,
        ]);
        $form->submit(['title' => 'Comptine libre', 'body' => 'Préparer les foulards.', 'lyrics' => "Un\nDeux\n", 'gestures' => "\nLever les bras\n"]);
        self::assertTrue($form->isSynchronized());
        self::assertSame("Un\nDeux\n", $form->getData()->lyrics);
        self::assertSame("\nLever les bras\n", $form->getData()->gestures);
    }

    public function testComposerOffersTheWholeFreeSequenceInOneForm(): void
    {
        self::bootKernel(['environment' => 'test']);

        $form = self::getContainer()->get('form.factory')->create(SessionSequenceType::class, new SessionSequenceFormModel(), [
            'activity_only' => true,
        ]);

        self::assertFalse($form->has('type'));
        self::assertTrue($form->has('title'));
        self::assertTrue($form->has('role'));
        self::assertTrue($form->has('instrumentUuids'));
        self::assertTrue($form->has('body'));
        self::assertTrue($form->has('lyrics'));
        self::assertTrue($form->has('gestures'));
        self::assertTrue($form->has('catalogMediaUuids'));
        self::assertFalse($form->has('media'));
    }
}
