<?php

namespace App\Enum;

enum WishlistCreationStep: string
{
    case FAMILY = 'family';
    case BABY = 'baby';
    case CONFIRM = 'confirm';


    public function number(): int
    {
        return match ($this) {
            self::FAMILY => 1,
            self::BABY => 2,
            self::CONFIRM => 3,
        };
    }


    public function next(): ?self
    {
        return match ($this) {
            self::FAMILY => self::BABY,
            self::BABY => self::CONFIRM,
            self::CONFIRM => null,
        };
    }


    public function previous(): ?self
    {
        return match ($this) {
            self::FAMILY => null,
            self::BABY => self::FAMILY,
            self::CONFIRM => self::BABY,
        };
    }
}
