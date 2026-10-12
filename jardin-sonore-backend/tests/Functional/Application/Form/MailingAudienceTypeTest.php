<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Form;

use App\Application\Form\MailingAudienceType;
use App\Application\Form\Model\MailingAudienceFormModel;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class MailingAudienceTypeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testFreeSubscribersSurviveFormRoundTrip(): void
    {
        $formModel = MailingAudienceFormModel::fromAudienceFilter(new NewsletterAudienceFilter(includeFreeSubscribers: true));
        self::assertTrue($formModel->toAudienceFilter()->includesFreeSubscribers());
    }

    public function testFreeSubscribersAreOptInAndSubmittedCheckboxIsPreserved(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $formModel = new MailingAudienceFormModel();
        $form = $formFactory->create(MailingAudienceType::class, $formModel);
        self::assertFalse($formModel->toAudienceFilter()->includesFreeSubscribers());
        self::assertTrue($form->has('includeFreeSubscribers'));
        $form->submit(['includeFreeSubscribers' => '1'], false);
        self::assertTrue($formModel->toAudienceFilter()->includesFreeSubscribers());
    }

    public function testLockedAudienceCannotChangeFreeSubscribers(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $formModel = new MailingAudienceFormModel();
        $form = $formFactory->create(MailingAudienceType::class, $formModel, ['locked' => true]);
        $form->submit(['includeFreeSubscribers' => '1'], false);
        self::assertFalse($formModel->toAudienceFilter()->includesFreeSubscribers());
    }

    public function testMaskEditorIncludesNameAndAudienceCriteria(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(MailingAudienceType::class, new MailingAudienceFormModel(), ['mask_mode' => true]);

        self::assertTrue($form->has('maskName'));
        self::assertTrue($form->has('organizationTypes'));
        self::assertTrue($form->has('municipalityInseeCodes'));
    }

    public function testEmptyMaskNameIsRejectedWithoutTypeError(): void
    {
        self::bootKernel(['environment' => 'test']);
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $formModel = new MailingAudienceFormModel();
        $form = $formFactory->create(MailingAudienceType::class, $formModel, ['mask_mode' => true]);

        $form->submit(['maskName' => ''], false);

        self::assertSame('', $formModel->maskName);
        self::assertFalse($form->isValid());
    }
}
