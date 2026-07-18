<?php

namespace App\Enums;

enum RequirementType: string
{
    case DIGITAL_FILE = 'digital_file';
    case PHYSICAL_DOCUMENT = 'physical_document';
    case PAYMENT = 'payment';
    case FORM_FIELD = 'form_field';
    case INTERNAL_VERIFICATION = 'internal_verification';
    case PREREQUISITE = 'prerequisite';
    case INFORMATION = 'information';
}
