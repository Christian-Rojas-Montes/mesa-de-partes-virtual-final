<?php

namespace Tests\Feature\Security;

use App\Models\ProcedureRequest;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\RequestDocument;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\NotificationPrivacySanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SensitiveDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_sensitive_download_has_policy_headers_controlled_name_and_audit(): void
    {
        Storage::fake('private');
        $owner = $this->user('Solicitante');
        $type = ProcedureType::factory()->create();
        $status = Status::factory()->create();
        $request = ProcedureRequest::factory()->create(['user_id' => $owner->id, 'procedure_type_id' => $type->id, 'status_id' => $status->id, 'tracking_code' => 'MPV-2026-SEC001']);
        $requirement = ProcedureRequirement::factory()->create(['procedure_type_id' => $type->id, 'sensitive' => true]);
        Storage::disk('private')->put('requests/'.$request->id.'/documento-seguro.pdf', 'contenido ficticio');
        $document = RequestDocument::create(['procedure_request_id' => $request->id, 'procedure_requirement_id' => $requirement->id, 'disk' => 'private', 'path' => 'requests/'.$request->id.'/documento-seguro.pdf', 'stored_name' => 'uuid.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 18, 'checksum_sha256' => hash('sha256', 'contenido ficticio')]);

        $response = $this->actingAs($owner)->get(route('applicant.procedure-requests.documents.download', [$request, $document]))->assertOk();
        $response->assertHeader('x-content-type-options', 'nosniff')->assertHeader('x-robots-tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
        $this->assertStringContainsString('mpv-2026-sec001-documento-'.$document->id.'.pdf', strtolower($response->headers->get('content-disposition')));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'private_document_downloaded', 'auditable_id' => $request->id]);
    }

    public function test_idor_public_disk_mime_mismatch_and_traversal_are_rejected(): void
    {
        Storage::fake('private');
        $owner = $this->user('Solicitante');
        $outsider = $this->user('Solicitante');
        $type = ProcedureType::factory()->create();
        $status = Status::factory()->create();
        $request = ProcedureRequest::factory()->create(['user_id' => $owner->id, 'procedure_type_id' => $type->id, 'status_id' => $status->id]);
        $document = RequestDocument::create(['procedure_request_id' => $request->id, 'disk' => 'private', 'path' => '../secret.pdf', 'stored_name' => 'secret.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'checksum_sha256' => str_repeat('a', 64)]);
        $this->actingAs($outsider)->get(route('applicant.procedure-requests.documents.download', [$request, $document]))->assertForbidden();
        $this->actingAs($owner)->get(route('applicant.procedure-requests.documents.download', [$request, $document]))->assertNotFound();
        $document->update(['disk' => 'public', 'path' => 'document.pdf']);
        $this->get(route('applicant.procedure-requests.documents.download', [$request, $document]))->assertForbidden();
    }

    public function test_sensitive_terms_are_removed_from_notifications_and_retention_never_deletes_automatically(): void
    {
        $message = NotificationPrivacySanitizer::sanitize('Adjuntamos partida de nacimiento, copias del DNI y actas de titulación.');
        $this->assertStringNotContainsString('partida de nacimiento', mb_strtolower($message));
        $this->assertStringNotContainsString('dni', mb_strtolower($message));
        $this->assertStringNotContainsString('actas de titulación', mb_strtolower($message));
        $this->assertFalse(config('document-retention.automatic_deletion_enabled'));
        $this->assertTrue(config('document-retention.legal_hold_prevents_deletion'));
    }

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['description' => 'Rol ficticio', 'active' => true]);

        return User::factory()->for($role)->create(['active' => true]);
    }
}
