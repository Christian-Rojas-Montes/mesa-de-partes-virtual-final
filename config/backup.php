<?php

return [
    'disk' => env('BACKUP_DISK', 'backup-local'),
    'retention_count' => (int) env('BACKUP_RETENTION_COUNT', 14),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'database_connection' => env('BACKUP_DATABASE_CONNECTION'),
    'temporary_path' => storage_path('app/backup-temp'),
    'private_documents_path' => storage_path('app/private/procedure-documents'),
    'configuration_files' => [
        'composer.lock', 'package-lock.json', 'public/manifest.webmanifest',
    ],
    'configuration_directories' => ['config', 'database/migrations'],
];
