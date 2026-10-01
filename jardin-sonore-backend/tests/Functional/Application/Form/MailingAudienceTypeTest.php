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
}
