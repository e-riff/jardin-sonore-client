<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\Mailing\MailingCampaign;
use Symfony\Component\Validator\Constraints as Assert;

final class EditMailingCampaignFormModel
{
    #[Assert\NotBlank]
    public string $internalTitle = '';

    #[Assert\NotBlank]
    public string $emailSubject = '';

    #[Assert\NotBlank]
    public string $publicTitle = '';

    #[Assert\NotBlank]
    public string $mainText = '';

    #[Assert\NotBlank]
    public string $templateKey = 'default';

    public static function fromMailingCampaign(MailingCampaign $mailingCampaign): self
    {
        $formModel = new self();
        $formModel->internalTitle = $mailingCampaign->getInternalTitle();
        $formModel->emailSubject = $mailingCampaign->getEmailSubject();
        $formModel->publicTitle = $mailingCampaign->getPublicTitle();
        $formModel->mainText = $mailingCampaign->getMainText();
        $formModel->templateKey = $mailingCampaign->getTemplateKey();

        return $formModel;
    }
}
