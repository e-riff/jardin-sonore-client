<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

enum NewsletterAudienceRadiusOrigin: string
{
    case MUNICIPALITY = 'municipality';
    case HOME = 'home';
}
