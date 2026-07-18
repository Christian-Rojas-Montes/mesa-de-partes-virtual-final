<?php

namespace App\Enums;

enum TitleModality: string
{
    case APPLICATION_WORK = 'application_work';
    case PROFESSIONAL_EXAM = 'professional_exam';

    public function label(): string
    {
        return match ($this) {
            self::APPLICATION_WORK => 'Trabajo de Aplicación Profesional',
            self::PROFESSIONAL_EXAM => 'Examen de Suficiencia Profesional',
        };
    }
}
