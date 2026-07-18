<?php

namespace Tests\Feature\Deployment;

use App\Services\DeploymentReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_required_configuration_is_reported_without_values(): void
    {
        config(['app.key' => '', 'app.url' => '', 'mail.from.address' => null]);
        $errors = app(DeploymentReadinessService::class)->configurationErrors();
        $this->assertContains('Falta la configuración obligatoria APP_KEY.', $errors);
        $this->assertContains('Falta la configuración obligatoria APP_URL.', $errors);
        $this->assertContains('Falta la configuración obligatoria MAIL_FROM_ADDRESS.', $errors);
    }

    public function test_health_check_verifies_database_storage_and_queue_without_sensitive_details(): void
    {
        Storage::fake('private');
        config(['deployment.health_storage_disk' => 'private']);
        $response = $this->get(route('check'))->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('checks.database', true)->assertJsonPath('checks.storage', true)->assertJsonPath('checks.queue', true);
        $response->assertHeader('cache-control', 'no-store, private')->assertHeader('x-robots-tag', 'noindex, nofollow');
        $content = $response->getContent();
        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringNotContainsString('storage_path', $content);
    }

    public function test_health_check_returns_service_unavailable_when_persistent_storage_fails(): void
    {
        config(['deployment.health_storage_disk' => 'nonexistent-disk']);
        $this->get(route('check'))->assertStatus(503)->assertJsonPath('status', 'degraded')->assertJsonPath('checks.storage', false);
    }

    public function test_deployment_command_fails_for_incomplete_configuration(): void
    {
        config(['app.key' => '']);
        $this->artisan('deployment:check')->assertFailed()->expectsOutputToContain('Falta la configuración obligatoria APP_KEY.');
    }
}
