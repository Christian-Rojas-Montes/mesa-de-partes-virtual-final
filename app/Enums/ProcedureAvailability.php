<?php

namespace App\Enums;

enum ProcedureAvailability: string
{
    case AVAILABLE = 'available';
    case UPCOMING = 'upcoming';
    case CLOSED = 'closed';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Disponible',
            self::UPCOMING => 'Próximo',
            self::CLOSED => 'Cerrado',
            self::SUSPENDED => 'Suspendido',
        };
    }
}
