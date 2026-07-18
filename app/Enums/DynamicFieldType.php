<?php

namespace App\Enums;

enum DynamicFieldType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case DATE = 'date';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case SELECT = 'select';
    case MULTISELECT = 'multiselect';
    case CHECKBOX = 'checkbox';
    case YEAR = 'year';
    case SEMESTER = 'semester';
    case SHIFT = 'shift';
    case CAREER = 'career';
    case COURSE_UNITS = 'course_units';
}
