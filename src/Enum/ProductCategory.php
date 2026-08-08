<?php

namespace App\Enum;

enum ProductCategory: string
{
    case MEAL = 'repas';
    case HYGIENE = 'hygiene';
    case AWAKENING = 'eveil';
    case TRAVEL = 'voyage';
    case BEDROOM = 'chambre';
    case CLOTHING = 'vêtements';
}
