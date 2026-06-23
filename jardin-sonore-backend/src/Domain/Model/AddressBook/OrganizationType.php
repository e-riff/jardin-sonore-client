<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum OrganizationType: string
{
    case CRECHE = 'creche';
    case MAIRIE = 'mairie';
    case RAM = 'ram';
    case MAM = 'mam';
    case MEDIATHEQUE = 'mediatheque';
    case CENTRE = 'centre';
    case GARDERIE = 'garderie';
}
