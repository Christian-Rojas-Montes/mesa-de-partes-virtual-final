<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use App\Services\SystemBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SystemBackup extends Command
{
    protected $signature = 'system:backup {--dry-run : Mostrar el plan sin crear archivos} {--verify= : Verificar un respaldo existente} {--restore-temp= : Verificar y extraer un respaldo a una ubicación temporal}';

    protected $description = 'Crea, verifica o extrae temporalmente un respaldo integral seguro';

    public function handle(SystemBackupService $backups): int
    {
        if ($this->option('verify') && $this->option('restore-temp')) {
            $this->error('Usa solamente --verify o --restore-temp.');

            return self::INVALID;
        }

        $path = $this->option('verify') ?: $this->option('restore-temp');
        if ($path) {
            return $this->verify($backups, (string) $path, (bool) $this->option('restore-temp'));
        }

        try {
            $result = $backups->create((bool) $this->option('dry-run'));
            $this->info($result['result'] === 'dry-run' ? 'Simulación completada; no se crearon archivos.' : 'Respaldo creado y verificado estructuralmente.');
            $this->table(['Dato', 'Valor'], collect($result)->map(fn ($value, $key) => [$key, is_array($value) ? implode(', ', $value) : $value])->values()->all());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('El respaldo falló. Revisa el registro de respaldos y los logs protegidos.');

            return self::FAILURE;
        }
    }

    private function verify(SystemBackupService $backups, string $path, bool $restore): int
    {
        $log = BackupLog::create(['started_at' => now(), 'type' => $restore ? 'temporary_restore' : 'verification', 'responsible' => 'artisan', 'result' => 'running', 'logical_location' => config('backup.disk').':'.basename($path)]);
        try {
            $result = $backups->verify($path, $restore);
            $log->update(['finished_at' => now(), 'result' => 'success', 'checksum_sha256' => $result['checksum_sha256']]);
            $this->info($restore ? 'Respaldo verificado y extraído únicamente en ubicación temporal.' : 'Integridad del respaldo verificada.');
            $this->table(['Dato', 'Valor'], collect($result)->map(fn ($value, $key) => [$key, $value ?? '—'])->values()->all());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $log->update(['finished_at' => now(), 'result' => 'failed', 'error' => Str::limit($exception->getMessage(), 2000)]);
            $this->error('La verificación falló: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
