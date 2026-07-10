<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

enum MailingAudienceGeographicMode: string
{
    case MUNICIPALITIES = 'municipalities';
    case HOME_RADIUS = 'home_radius';
    case MUNICIPALITY_RADIUS = 'municipality_radius';
    case CUSTOM_RADIUS = 'custom_radius';
}
