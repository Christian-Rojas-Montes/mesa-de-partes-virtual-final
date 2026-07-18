<?php

namespace Database\Seeders;

use App\Services\InstitutionalCatalogSyncService;
use Illuminate\Database\Seeder;

class InstitutionalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('El catálogo institucional solo se siembra en desarrollo o pruebas.');

            return;
        }
        app(InstitutionalCatalogSyncService::class)->synchronize(true);
    }
}
