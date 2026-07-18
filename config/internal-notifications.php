<?php

return [
    'email_enabled' => (bool) env('INTERNAL_NOTIFICATIONS_EMAIL_ENABLED', false),
    'queue_connection' => env('INTERNAL_NOTIFICATIONS_QUEUE_CONNECTION', 'sync'),
];
