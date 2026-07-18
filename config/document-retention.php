<?php

return [
    'automatic_deletion_enabled' => false,
    'default_retention_years' => (int) env('DOCUMENT_RETENTION_YEARS', 10),
    'sensitive_retention_years' => (int) env('SENSITIVE_DOCUMENT_RETENTION_YEARS', 10),
    'legal_hold_prevents_deletion' => true,
];
