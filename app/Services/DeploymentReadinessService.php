<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DeploymentReadinessService
{
    /** @return list<string> */
    public function configurationErrors(): array
    {
        $errors = [];
        $required = [
            'APP_KEY' => config('app.key'), 'APP_URL' => config('app.url'),
            'DB_CONNECTION' => config('database.default'), 'FILESYSTEM_PRIVATE' => config('filesystems.disks.private'),
            'SESSION_DRIVER' => config('session.driver'), 'CACHE_STORE' => config('cache.default'),
            'QUEUE_CONNECTION' => config('queue.default'), 'MAIL_FROM_ADDRESS' => config('mail.from.address'),
            'BACKUP_DISK' => config('backup.disk'),
        ];
        foreach ($required as $name => $value) {
            if ($value === null || $value === '' || $value === []) {
                $errors[] = "Falta la configuración obligatoria {$name}.";
            }
        }
        foreach (config('deployment.required_php_extensions', []) as $extension) {
            if (! extension_loaded($extension)) {
                $errors[] = "Falta la extensión PHP {$extension}.";
            }
        }
        $connection = config('database.connections.'.config('database.default'));
        if (in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            foreach (['host', 'database', 'username'] as $key) {
                if (($connection[$key] ?? '') === '') {
                    $errors[] = 'Falta la configuración DB_'.strtoupper($key).'.';
                }
            }
        }
        if (app()->environment('production')) {
            if (config('app.debug')) {
                $errors[] = 'APP_DEBUG debe ser false en producción.';
            }
            if (! Str::startsWith((string) config('app.url'), 'https://')) {
                $errors[] = 'APP_URL debe usar HTTPS en producción.';
            }
            if (! config('session.secure')) {
                $errors[] = 'SESSION_SECURE_COOKIE debe ser true bajo HTTPS.';
            }
            if (in_array(config('session.driver'), ['array', 'cookie'], true)) {
                $errors[] = 'SESSION_DRIVER debe ser persistente en producción.';
            }
            if (in_array(config('cache.default'), ['array', 'null'], true)) {
                $errors[] = 'CACHE_STORE debe ser persistente en producción.';
            }
            if (config('filesystems.default') === 'public' || config('backup.disk') === 'public') {
                $errors[] = 'Los discos de aplicación y respaldo no pueden ser públicos.';
            }
            if (config('internal-notifications.email_enabled') && in_array(config('mail.default'), ['log', 'array'], true)) {
                $errors[] = 'El correo habilitado requiere un transportador real en producción.';
            }
        }

        return $errors;
    }

    /** @return array{application: bool, database: bool, storage: bool, queue: bool} */
    public function health(): array
    {
        return [
            'application' => $this->configurationErrors() === [],
            'database' => $this->databaseHealthy(),
            'storage' => $this->storageHealthy(),
            'queue' => $this->queueHealthy(),
        ];
    }

    private function databaseHealthy(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function storageHealthy(): bool
    {
        $path = '.health/'.Str::uuid();
        try {
            $disk = Storage::disk(config('deployment.health_storage_disk'));
            $disk->put($path, 'ok');

            return $disk->exists($path) && $disk->delete($path);
        } catch (Throwable) {
            return false;
        }
    }

    private function queueHealthy(): bool
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        if (! is_string($driver) || in_array($driver, ['null'], true)) {
            return false;
        }
        if ($driver !== 'database') {
            return true;
        }
        try {
            return Schema::hasTable(config("queue.connections.{$connection}.table", 'jobs'));
        } catch (Throwable) {
            return false;
        }
    }
}
