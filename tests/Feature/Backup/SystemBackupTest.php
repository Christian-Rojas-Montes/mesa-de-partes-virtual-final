<?php

namespace Tests\Feature\Backup;

use App\Models\BackupLog;
use App\Services\SystemBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SystemBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $documents;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backup-local');
        config(['backup.disk' => 'backup-local', 'backup.retention_count' => 2]);
        $this->documents = storage_path('framework/testing/backup-documents-'.uniqid());
        mkdir($this->documents, 0777, true);
        file_put_contents($this->documents.'/documento-ficticio.pdf', 'contenido privado ficticio');
        config(['backup.private_documents_path' => $this->documents]);
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory($this->documents);
        app('files')->deleteDirectory(storage_path('app/backup-restore'));
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_backup_contains_database_private_documents_manifest_checksums_and_no_secrets(): void
    {
        $result = app(SystemBackupService::class)->create();
        Storage::disk('backup-local')->assertExists($result['name']);
        Storage::disk('backup-local')->assertExists($result['name'].'.sha256');
        $this->assertSame(hash('sha256', Storage::disk('backup-local')->get($result['name'])), $result['checksum_sha256']);
        $verified = app(SystemBackupService::class)->verify($result['name'], true);
        $this->assertSame('verified', $verified['result']);
        $manifest = file_get_contents($verified['temporary_restore_path'].'/manifest.json');
        $this->assertStringContainsString('private-documents/documento-ficticio.pdf', $manifest);
        $this->assertStringNotContainsString('DB_PASSWORD', $manifest);
        $this->assertDatabaseHas('backup_logs', ['result' => 'success', 'checksum_sha256' => $result['checksum_sha256']]);
    }

    public function test_dry_run_creates_nothing_and_missing_or_changed_archive_fails_verification(): void
    {
        $plan = app(SystemBackupService::class)->create(true);
        $this->assertSame('dry-run', $plan['result']);
        $this->assertSame(0, BackupLog::count());
        $this->expectException(RuntimeException::class);
        app(SystemBackupService::class)->verify('missing.tar.gz');
    }

    public function test_database_error_is_recorded_without_password(): void
    {
        config(['backup.database_connection' => 'unsupported', 'database.connections.unsupported' => ['driver' => 'pgsql', 'database' => 'ficticia', 'prefix' => '', 'password' => 'SECRETO-NO-REGISTRAR']]);
        try {
            app(SystemBackupService::class)->create();
            $this->fail('El motor no soportado debía fallar.');
        } catch (RuntimeException) {
            $log = BackupLog::latest('id')->firstOrFail();
            $this->assertSame('failed', $log->result);
            $this->assertStringNotContainsString('SECRETO-NO-REGISTRAR', (string) $log->error);
        }
    }

    public function test_retention_keeps_configured_number_and_verification_detects_checksum_change(): void
    {
        foreach ([0, 1, 2] as $seconds) {
            Carbon::setTestNow(now()->startOfDay()->addSeconds($seconds));
            $last = app(SystemBackupService::class)->create();
        }
        $archives = collect(Storage::disk('backup-local')->files())->filter(fn ($path) => str_ends_with($path, '.tar.gz'));
        $this->assertCount(2, $archives);
        Storage::disk('backup-local')->put($last['name'], 'alterado');
        $this->expectException(RuntimeException::class);
        app(SystemBackupService::class)->verify($last['name']);
    }
}
