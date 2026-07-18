<?php

namespace App\Enums;

enum PrerequisiteType: string
{
    case APPROVED_PROCEDURE = 'approved_procedure';
    case CERTIFICATE = 'certificate';
    case ACADEMIC_RESULT = 'academic_result';
    case ADMINISTRATIVE_VERIFICATION = 'administrative_verification';
}
