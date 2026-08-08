<?php

namespace App\Enum;

enum ProductStatus: string
{
    case AVAILABLE = 'pas acheté';
    case BUYING = 'en cours d\'achat';
    case PURCHASED = 'acheté';
}
