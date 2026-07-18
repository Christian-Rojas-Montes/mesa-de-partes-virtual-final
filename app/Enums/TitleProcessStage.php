<?php

namespace App\Enums;

enum TitleProcessStage: string
{
    case INITIAL_FILE = 'initial_file';
    case REQUIREMENTS_REVIEW = 'requirements_review';
    case ACADEMIC_AREA = 'academic_area';
    case SCHEDULED = 'scheduled';
    case RESULT_RECORDED = 'result_recorded';
    case FINAL_FILE = 'final_file';
    case EXTERNAL_REGISTRATION = 'external_registration';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case DELIVERED = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL_FILE => 'Expediente inicial', self::REQUIREMENTS_REVIEW => 'Revisión de requisitos',
            self::ACADEMIC_AREA => 'Derivado al área académica', self::SCHEDULED => 'Programado',
            self::RESULT_RECORDED => 'Resultado registrado', self::FINAL_FILE => 'Expediente final conformado',
            self::EXTERNAL_REGISTRATION => 'Registro ante la entidad correspondiente', self::READY_FOR_DELIVERY => 'Documento listo para entrega',
            self::DELIVERED => 'Entregado y finalizado',
        };
    }
}
