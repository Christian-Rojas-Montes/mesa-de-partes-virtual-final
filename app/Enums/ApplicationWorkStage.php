<?php

namespace App\Enums;

enum ApplicationWorkStage: string
{
    case PROPOSAL = 'proposal';
    case APPROVAL = 'approval';
    case GRADUATE_CERTIFICATE = 'graduate_certificate';
    case DEFENSE_FILE = 'defense_file';
    case SCHEDULING = 'scheduling';
    case RESULT = 'result';

    public function label(): string
    {
        return match ($this) {
            self::PROPOSAL => 'Propuesta',self::APPROVAL => 'Aprobación',self::GRADUATE_CERTIFICATE => 'Constancia de egresado',self::DEFENSE_FILE => 'Expediente de sustentación',self::SCHEDULING => 'Programación',self::RESULT => 'Resultado'
        };
    }
}
