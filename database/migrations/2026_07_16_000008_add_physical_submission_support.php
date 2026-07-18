<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->string('physical_submission_location', 250)->nullable()->after('requires_physical_delivery');
            $table->string('physical_submission_schedule', 250)->nullable()->after('physical_submission_location');
            $table->unsignedSmallInteger('physical_submission_deadline_days')->nullable()->after('physical_submission_schedule');
        });
        Schema::table('procedure_variants', function (Blueprint $table) {
            $table->string('physical_submission_location', 250)->nullable()->after('requires_physical_delivery');
            $table->string('physical_submission_schedule', 250)->nullable()->after('physical_submission_location');
            $table->unsignedSmallInteger('physical_submission_deadline_days')->nullable()->after('physical_submission_schedule');
        });
        Schema::create('request_physical_receptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('folio_count')->nullable();
            $table->unsignedSmallInteger('document_count');
            $table->json('presented_documents');
            $table->text('observations')->nullable();
            $table->foreignId('receiving_area_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->string('receipt_number', 100)->nullable()->unique();
            $table->string('evidence_disk', 30)->nullable();
            $table->string('evidence_path', 500)->nullable()->unique();
            $table->string('verification_result', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_physical_receptions');
        Schema::table('procedure_variants', fn (Blueprint $table) => $table->dropColumn(['physical_submission_location', 'physical_submission_schedule', 'physical_submission_deadline_days']));
        Schema::table('procedure_types', fn (Blueprint $table) => $table->dropColumn(['physical_submission_location', 'physical_submission_schedule', 'physical_submission_deadline_days']));
    }
};
