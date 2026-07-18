<?php

return [
    'health_storage_disk' => env('HEALTH_STORAGE_DISK', 'private'),
    'required_php_extensions' => ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'json', 'mbstring', 'openssl', 'pdo', 'session', 'tokenizer', 'xml', 'phar', 'zlib'],
];
