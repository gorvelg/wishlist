<?php

namespace App\Enum;

enum ProductStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case BUYING = 'buying';
    case FUNDED = 'funded';
    case PURCHASED = 'purchased';

    public function toFrench(): string{
        return match ($this){
            self::AVAILABLE => 'disponible',
            self::RESERVED => 'reservé',
            self::BUYING => 'en cours d\'achat',
            self::FUNDED => 'financé',
            self::PURCHASED => 'acheté',
        };
    }
}

