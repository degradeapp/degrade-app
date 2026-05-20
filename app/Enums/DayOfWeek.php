<?php

namespace App\Enums;

enum DayOfWeek: int
{
    case Monday = 0;
    case Tuesday = 1;
    case Wednesday = 2;
    case Thursday = 3;
    case Friday = 4;
    case Saturday = 5;
    case Sunday = 6;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Segunda',
            self::Tuesday => 'Terça',
            self::Wednesday => 'Quarta',
            self::Thursday => 'Quinta',
            self::Friday => 'Sexta',
            self::Saturday => 'Sábado',
            self::Sunday => 'Domingo',
        };
    }
}
