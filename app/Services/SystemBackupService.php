<?php

namespace App\Services;

use App\Models\BackupLog;
use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class SystemBackupService
{
    public function __construct(private readonly Filesystem $files) {}

    /** @return array<string, mixed> */
    public function create(bool $dryRun = false): array
    {
        $environment = preg_replace('/[^a-z0-9_-]/i', '-', app()->environment());
        $name = 'mesa-partes-'.$environment.'-'.now()->format('Ymd-His');
        $logicalPath = $name.'.tar.gz';
        $plan = ['name' => $logicalPath, 'disk' => config('backup.disk'), 'includes' => ['database', 'private-documents', 'safe-configuration', 'manifest', 'checksums']];

        if ($dryRun) {
            return $plan + ['result' => 'dry-run'];
        }

        $log = BackupLog::create(['started_at' => now(), 'type' => 'full', 'responsible' => $this->responsible(), 'result' => 'running', 'logical_location' => config('backup.disk').':'.$logicalPath]);
        $work = $this->temporaryDirectory($name);

        try {
            $payload = $work.DIRECTORY_SEPARATOR.'payload';
            $this->files->ensureDirectoryExists($payload);
            $this->backupDatabase($payload.DIRECTORY_SEPARATOR.'database');
            $this->copyPrivateDocuments($payload.DIRECTORY_SEPARATOR.'private-documents');
            $this->copySafeConfiguration($payload.DIRECTORY_SEPARATOR.'configuration');
            $manifest = $this->manifest($payload, $name);
            $this->files->put($payload.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            $archive = $this->compress($payload, $work.DIRECTORY_SEPARATOR.$name.'.tar');
            $checksum = hash_file('sha256', $archive) ?: throw new RuntimeException('No fue posible calcular el checksum del respaldo.');
            $size = filesize($archive) ?: 0;
            $disk = Storage::disk(config('backup.disk'));
            $stream = fopen($archive, 'rb') ?: throw new RuntimeException('No fue posible leer el respaldo comprimido.');
            try {
                $disk->writeStream($logicalPath, $stream);
            } finally {
                fclose($stream);
            }
            $disk->put($logicalPath.'.sha256', $checksum.'  '.$logicalPath.PHP_EOL);
            $this->applyRetention($disk);
            $log->update(['finished_at' => now(), 'size_bytes' => $size, 'checksum_sha256' => $checksum, 'result' => 'success']);

            return $plan + ['result' => 'success', 'size_bytes' => $size, 'checksum_sha256' => $checksum];
        } catch (Throwable $exception) {
            $log->update(['finished_at' => now(), 'result' => 'failed', 'error' => Str::limit($this->safeError($exception->getMessage()), 2000)]);
            throw $exception;
        } finally {
            $this->files->deleteDirectory($work);
        }
    }

    /** @return array<string, mixed> */
    public function verify(string $logicalPath, bool $extractToTemporary = false): array
    {
        $logicalPath = $this->safeLogicalPath($logicalPath);
        $disk = Storage::disk(config('backup.disk'));
        if (! $disk->exists($logicalPath) || ! $disk->exists($logicalPath.'.sha256')) {
            throw new RuntimeException('Falta el respaldo o su archivo de checksum.');
        }
        $work = $this->temporaryDirectory('verify-'.Str::uuid());
        $localArchive = $work.DIRECTORY_SEPARATOR.basename($logicalPath);
        $input = $disk->readStream($logicalPath);
        $output = fopen($localArchive, 'wb');
        if (! is_resource($input) || $output === false) {
            throw new RuntimeException('No fue posible preparar el respaldo para verificación.');
        }
        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);

        try {
            $expected = strtok(trim($disk->get($logicalPath.'.sha256')), " \t");
            $actual = hash_file('sha256', $localArchive);
            if (! is_string($expected) || ! is_string($actual) || ! hash_equals($expected, $actual)) {
                throw new RuntimeException('El checksum del respaldo no coincide.');
            }
            $extract = $work.DIRECTORY_SEPARATOR.'extracted';
            $this->files->ensureDirectoryExists($extract);
            (new PharData($localArchive))->extractTo($extract, null, true);
            $payload = $extract.DIRECTORY_SEPARATOR.'payload';
            $manifestPath = $payload.DIRECTORY_SEPARATOR.'manifest.json';
            if (! $this->files->exists($manifestPath)) {
                throw new RuntimeException('El manifiesto del respaldo no existe.');
            }
            $manifest = json_decode($this->files->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            foreach ($manifest['files'] ?? [] as $file) {
                $path = $payload.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
                if (! $this->files->exists($path) || ! hash_equals($file['sha256'], hash_file('sha256', $path) ?: '')) {
                    throw new RuntimeException('Falló la integridad de un archivo del respaldo.');
                }
            }
            $restoredAt = null;
            if ($extractToTemporary) {
                $restoredAt = storage_path('app/backup-restore/'.pathinfo($logicalPath, PATHINFO_FILENAME).'-'.now()->format('Ymd-His'));
                $this->files->ensureDirectoryExists(dirname($restoredAt));
                $this->files->moveDirectory($payload, $restoredAt);
            }

            return ['result' => 'verified', 'checksum_sha256' => $actual, 'files' => count($manifest['files'] ?? []), 'temporary_restore_path' => $restoredAt];
        } finally {
            $this->files->deleteDirectory($work);
        }
    }

    private function backupDatabase(string $directory): void
    {
        $this->files->ensureDirectoryExists($directory);
        $connection = config('backup.database_connection') ?: config('database.default');
        $database = config("database.connections.{$connection}");
        if (($database['driver'] ?? null) === 'sqlite') {
            $source = $database['database'];
            if ($source === ':memory:') {
                $this->dumpMemorySqlite(DB::connection($connection)->getPdo(), $directory.DIRECTORY_SEPARATOR.'database.sql');
            } elseif (is_string($source) && $this->files->exists($source)) {
                DB::connection($connection)->statement('PRAGMA wal_checkpoint(FULL)');
                $this->files->copy($source, $directory.DIRECTORY_SEPARATOR.'database.sqlite');
            } else {
                throw new RuntimeException('No existe el archivo de base de datos SQLite.');
            }

            return;
        }
        if (! in_array($database['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('El motor de base de datos requiere un procedimiento de respaldo alternativo documentado.');
        }
        $target = $directory.DIRECTORY_SEPARATOR.'database.sql';
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('No fue posible crear el archivo de volcado.');
        }
        $command = [(string) config('backup.mysqldump_binary'), '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces', '--host='.(string) $database['host'], '--port='.(string) $database['port'], '--user='.(string) $database['username'], '--default-character-set='.(string) ($database['charset'] ?? 'utf8mb4'), '--', (string) $database['database']];
        $process = new Process($command, base_path(), ['MYSQL_PWD' => (string) ($database['password'] ?? '')]);
        $error = '';
        try {
            $process->setTimeout((float) env('BACKUP_PROCESS_TIMEOUT', 600))->run(function (string $type, string $buffer) use ($handle, &$error) {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                } else {
                    $error .= $buffer;
                }
            });
        } finally {
            fclose($handle);
        }
        if (! $process->isSuccessful()) {
            $this->files->delete($target);
            throw new RuntimeException('mysqldump falló: '.$this->safeError(Str::limit($error, 500)));
        }
    }

    private function dumpMemorySqlite(\PDO $pdo, string $target): void
    {
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('No fue posible crear el volcado SQLite.');
        }
        try {
            fwrite($handle, "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");
            $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($tables as $table) {
                fwrite($handle, $table['sql'].";\n");
                $identifier = '"'.str_replace('"', '""', $table['name']).'"';
                $rows = $pdo->query("SELECT * FROM {$identifier}");
                while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                    $values = array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row));
                    fwrite($handle, "INSERT INTO {$identifier} VALUES(".implode(',', $values).");\n");
                }
            }
            fwrite($handle, "COMMIT;\nPRAGMA foreign_keys=ON;\n");
        } finally {
            fclose($handle);
        }
    }

    private function copyPrivateDocuments(string $target): void
    {
        $source = config('backup.private_documents_path');
        $this->files->ensureDirectoryExists($target);
        if ($this->files->isDirectory($source)) {
            $this->copyTree($source, $target);
        }
    }

    private function copySafeConfiguration(string $target): void
    {
        $this->files->ensureDirectoryExists($target);
        foreach (config('backup.configuration_files') as $relative) {
            if ($this->files->exists(base_path($relative))) {
                $destination = $target.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
                $this->files->ensureDirectoryExists(dirname($destination));
                $this->files->copy(base_path($relative), $destination);
            }
        }
        foreach (config('backup.configuration_directories') as $relative) {
            if ($this->files->isDirectory(base_path($relative))) {
                $this->copyTree(base_path($relative), $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));
            }
        }
    }

    private function copyTree(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink() || ! $item->isFile()) {
                continue;
            } $relative = substr($item->getPathname(), strlen($source) + 1);
            if (preg_match('#(^|[\\/])(tmp|temp|logs?|backups?)([\\/]|$)#i', $relative)) {
                continue;
            } $destination = $target.DIRECTORY_SEPARATOR.$relative;
            $this->files->ensureDirectoryExists(dirname($destination));
            $this->files->copy($item->getPathname(), $destination);
        }
    }

    private function manifest(string $payload, string $name): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($payload, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($payload) + 1));
                $files[] = ['path' => $relative, 'size' => $item->getSize(), 'sha256' => hash_file('sha256', $item->getPathname())];
            }
        }
        usort($files, fn ($a, $b) => $a['path'] <=> $b['path']);

        $connection = config('backup.database_connection') ?: config('database.default');

        return ['format_version' => 1, 'name' => $name, 'created_at' => now()->toIso8601String(), 'environment' => app()->environment(), 'database_driver' => config("database.connections.{$connection}.driver"), 'files' => $files, 'excludes' => ['.env', 'passwords', 'temporary-files', 'logs']];
    }

    private function compress(string $payload, string $tar): string
    {
        $phar = new PharData($tar);
        $phar->buildFromDirectory(dirname($payload));
        $phar->compress(\Phar::GZ);
        unset($phar);
        $this->files->delete($tar);

        return $tar.'.gz';
    }

    private function applyRetention($disk): void
    {
        $limit = max(1, (int) config('backup.retention_count'));
        $archives = collect($disk->files())->filter(fn ($f) => str_ends_with($f, '.tar.gz'))->sort()->values();
        foreach ($archives->take(max(0, $archives->count() - $limit)) as $archive) {
            $disk->delete([$archive, $archive.'.sha256']);
        }
    }

    private function temporaryDirectory(string $name): string
    {
        $root = config('backup.temporary_path');
        $this->files->ensureDirectoryExists($root);
        $path = $root.DIRECTORY_SEPARATOR.preg_replace('/[^a-z0-9_-]/i', '-', basename($name));
        $this->files->ensureDirectoryExists($path);

        return $path;
    }

    private function safeLogicalPath(string $path): string
    {
        if ($path !== basename($path) || ! preg_match('/^[a-z0-9._-]+\.tar\.gz$/i', $path)) {
            throw new RuntimeException('El nombre lógico del respaldo no es válido.');
        }

        return $path;
    }

    private function responsible(): string
    {
        return Str::limit((string) (getenv('USERNAME') ?: getenv('USER') ?: 'artisan'), 150, '');
    }

    private function safeError(string $message): string
    {
        $password = (string) config('database.connections.'.config('database.default').'.password');

        return $password !== '' ? str_replace($password, '[REDACTED]', $message) : $message;
    }
}
