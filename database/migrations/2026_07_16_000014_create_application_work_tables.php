<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_work_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_process_id')->unique()->constrained()->restrictOnDelete();
            $table->string('current_stage', 40);
            $table->string('title', 250);
            $table->text('problem');
            $table->text('objective');
            $table->string('study_program', 200);
            $table->string('proposed_advisor', 200);
            $table->foreignId('project_document_id')->constrained('request_documents')->restrictOnDelete();
            $table->date('proposal_date');
            $table->string('review_result', 30)->nullable();
            $table->text('approval_observations')->nullable();
            $table->foreignId('approval_resolution_document_id')->nullable()->constrained(
                'request_documents',
                indexName: 'application_work_approval_document_fk',
            )->restrictOnDelete();
            $table->string('assigned_advisor', 200)->nullable();
            $table->date('approved_at')->nullable();
            $table->date('execution_deadline')->nullable();
            $table->decimal('similarity_percent', 5, 2)->nullable();
            $table->string('originality_result', 30)->nullable();
            $table->decimal('grade', 5, 2)->nullable();
            $table->foreignId('result_minutes_document_id')->nullable()->constrained('request_documents')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('application_work_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_work_project_id')->constrained()->restrictOnDelete();
            $table->string('name_snapshot', 200);
            $table->string('study_program_snapshot', 200);
            $table->boolean('is_lead')->default(false);
            $table->timestamps();
        });
        Schema::create('application_work_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_work_project_id')->constrained(
                indexName: 'application_work_requirement_project_fk',
            )->restrictOnDelete();
            $table->string('stage', 40);
            $table->string('code', 60);
            $table->string('label_snapshot', 250);
            $table->boolean('physical')->default(false);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('status', 30)->default('pending');
            $table->foreignId('request_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['application_work_project_id', 'stage', 'code'], 'application_work_requirement_unique');
        });
        Schema::create('application_work_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_work_project_id')->constrained()->restrictOnDelete();
            $table->string('from_stage', 40)->nullable();
            $table->string('to_stage', 40);
            $table->string('action', 60);
            $table->text('description');
            $table->json('snapshot')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_work_events');
        Schema::dropIfExists('application_work_requirements');
        Schema::dropIfExists('application_work_members');
        Schema::dropIfExists('application_work_projects');
    }
};
