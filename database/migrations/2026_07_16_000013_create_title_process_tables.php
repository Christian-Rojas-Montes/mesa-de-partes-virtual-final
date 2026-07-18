<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->unique()->constrained()->restrictOnDelete();
            $table->string('modality', 40);
            $table->string('current_stage', 50);
            $table->string('attempt_or_call', 100)->nullable();
            $table->json('eligibility_declared');
            $table->json('eligibility_verified')->nullable();
            $table->foreignId('eligibility_verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('eligibility_verified_at')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('result', 30)->nullable();
            $table->text('result_observation')->nullable();
            $table->foreignId('result_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('result_recorded_at')->nullable();
            $table->timestamp('final_file_completed_at')->nullable();
            $table->timestamps();
            $table->index(['current_stage', 'modality']);
        });
        Schema::create('title_stage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_process_id')->constrained()->restrictOnDelete();
            $table->string('from_stage', 50)->nullable();
            $table->string('to_stage', 50);
            $table->string('action', 60);
            $table->text('description');
            $table->json('snapshot')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['title_process_id', 'created_at']);
        });
        Schema::create('title_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_process_id')->constrained()->restrictOnDelete();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('title_schedules')->restrictOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('place', 200);
            $table->json('jury_or_responsibles')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('title_process_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_process_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_document_id')->constrained()->restrictOnDelete();
            $table->string('stage', 50);
            $table->string('document_kind', 30);
            $table->string('label_snapshot', 200);
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(
                ['title_process_id', 'request_document_id'],
                'title_process_document_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_process_documents');
        Schema::dropIfExists('title_schedules');
        Schema::dropIfExists('title_stage_events');
        Schema::dropIfExists('title_processes');
    }
};
