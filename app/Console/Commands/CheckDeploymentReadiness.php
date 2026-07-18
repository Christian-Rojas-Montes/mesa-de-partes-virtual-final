<?php

namespace App\Console\Commands;

use App\Services\DeploymentReadinessService;
use Illuminate\Console\Command;

class CheckDeploymentReadiness extends Command
{
    protected $signature = 'deployment:check {--health : Incluir comprobaciones de base, storage y colas}';

    protected $description = 'Valida la configuración requerida antes de un despliegue';

    public function handle(DeploymentReadinessService $readiness): int
    {
        $errors = $readiness->configurationErrors();
        foreach ($errors as $error) {
            $this->error($error);
        }
        if ($this->option('health')) {
            foreach ($readiness->health() as $check => $ok) {
                $this->line(sprintf('%s: %s', $check, $ok ? 'OK' : 'ERROR'));
            }
        }
        if ($errors !== []) {
            return self::FAILURE;
        }
        $this->info('La configuración requerida está completa.');

        return self::SUCCESS;
    }
}
