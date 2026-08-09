<?php

namespace App\Enum;

enum ProductCategory: string
{
    case MEAL = 'meal';
    case HYGIENE = 'hygiene';
    case AWAKENING = 'awakening';
    case TRAVEL = 'travel';
    case BEDROOM = 'bedroom';
    case CLOTHING = 'clothing';

    public function toFrench(){
        return match ($this){
            self::MEAL => 'repas',
            self::HYGIENE => 'hygiene',
            self::AWAKENING => 'eveil',
            self::TRAVEL => 'voyage',
            self::BEDROOM => 'chambre',
            self::CLOTHING => 'vêtements',
        };
    }
}
