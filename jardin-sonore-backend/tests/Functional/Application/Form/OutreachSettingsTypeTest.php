<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Form;

use App\Application\Form\Model\OutreachSettingsFormModel;
use App\Application\Form\OutreachSettingsType;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class OutreachSettingsTypeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testZeroDisablesWindowAndNegativeValueIsAFieldError(): void
    {
        self::bootKernel(['environment' => 'test']);
        $factory = self::getContainer()->get(FormFactoryInterface::class);
        foreach ([0 => true, 9 => true, -1 => false, 521 => false] as $value => $valid) {
            $model = new OutreachSettingsFormModel(6);
            $form = $factory->create(OutreachSettingsType::class, $model, ['csrf_protection' => false]);
            $form->submit(['weeks' => (string) $value]);
            self::assertSame($valid, $form->isValid());
            if ($valid) {
                self::assertSame($value, $model->weeks);
            } else {
                self::assertNotEmpty(iterator_to_array($form->get('weeks')->getErrors(true)));
            }
        }
    }
}
