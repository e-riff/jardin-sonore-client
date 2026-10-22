<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

enum OrganizationType: string
{
    case CRECHE = 'creche';
    case MAIRIE = 'mairie';
    case RPE = 'rpe';
    case MAM = 'mam';
    case MEDIATHEQUE = 'mediatheque';
    case CENTRE = 'centre';
    case MJC = 'mjc';
    case LIEU_PARENTALITE = 'lieu_parentalite';
    case LUDOTHEQUE = 'ludotheque';
    case GARDERIE = 'garderie';
    case CAFE_PARENTS = 'cafe_parents';
    case LIBRAIRIE_JEUNESSE = 'librairie_jeunesse';
    case FESTIVAL_EVENEMENT = 'festival_evenement';
    case SALLE_SPECTACLE = 'salle_spectacle';
    case TEST = 'test';
}
