<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;

interface NewsletterRendererInterface
{
    public function render(MailingCampaign $mailingCampaign): RenderedNewsletter;
}
