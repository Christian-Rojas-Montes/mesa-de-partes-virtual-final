<?php

namespace App\Enums;

enum PaymentTiming: string
{
    case BEFORE = 'before';
    case AFTER = 'after';
}
