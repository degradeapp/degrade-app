<?php

namespace App\Modules\User\Enums;

enum UserRole: string
{
    case owner = 'owner';
    case manager = 'manager';
    case receptionist = 'receptionist';
    case barber = 'barber';

    public function label(): string
    {
        return match ($this) {
            self::owner => 'Proprietário',
            self::manager => 'Gerente',
            self::receptionist => 'Recepcionista',
            self::barber => 'Barbeiro',
        };
    }
}
