<?php

namespace App\Console\Commands;

use App\Services\InstitutionalCatalogSyncService;
use Illuminate\Console\Command;

class SyncInstitutionalCatalog extends Command
{
    protected $signature = 'catalog:sync-institutional {--dry-run : Simula sin escribir} {--apply : Aplica los cambios} {--catalog-version= : Versión esperada del catálogo}';

    protected $description = 'Sincroniza de forma segura el catálogo institucional versionado';

    public function handle(InstitutionalCatalogSyncService $service): int
    {
        if ((bool) $this->option('dry-run') === (bool) $this->option('apply')) {
            $this->error('Indique exactamente una opción: --dry-run o --apply.');

            return self::INVALID;
        }
        try {
            $report = $service->synchronize((bool) $this->option('apply'), $this->option('catalog-version'));
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }
        $this->info("Catálogo {$report['version']} ({$report['mode']})");
        foreach (['created' => 'Altas', 'changed' => 'Cambios', 'unchanged' => 'Sin cambios', 'conflicts' => 'Advertencias'] as $key => $label) {
            $this->line("{$label}: ".count($report[$key]));
            foreach ($report[$key] as $item) {
                $this->line(" - {$item}");
            }
        }

        return empty($report['conflicts']) ? self::SUCCESS : self::FAILURE;
    }
}
