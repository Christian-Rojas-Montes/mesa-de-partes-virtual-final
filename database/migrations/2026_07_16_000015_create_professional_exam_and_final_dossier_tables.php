<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_exam_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_process_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('experience_months');
            $table->string('experience_basis', 30);
            $table->string('current_stage', 30)->default('requirements');
            $table->timestamps();
        });
        Schema::create('professional_exam_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_exam_profile_id')->constrained(
                indexName: 'professional_exam_requirement_profile_fk',
            )->restrictOnDelete();
            $table->string('code', 60);
            $table->string('label_snapshot', 250);
            $table->boolean('physical')->default(false);
            $table->boolean('sensitive')->default(false);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('status', 30)->default('pending');
            $table->foreignId('request_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('observation')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['professional_exam_profile_id', 'code'], 'professional_exam_requirement_unique');
        });
        Schema::create('professional_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_exam_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('opportunity');
            $table->foreignId('title_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('theory_weight', 5, 2);
            $table->decimal('practical_weight', 5, 2);
            $table->decimal('theory_grade', 5, 2)->nullable();
            $table->decimal('practical_grade', 5, 2)->nullable();
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->string('result', 30)->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
            $table->unique(['professional_exam_profile_id', 'opportunity'], 'professional_exam_opportunity_unique');
        });
        Schema::create('title_final_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_process_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('collecting');
            $table->text('observations')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('registration_code', 100)->nullable();
            $table->date('registered_at')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('pickup_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('title_final_dossier_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_final_dossier_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('label_snapshot', 250);
            $table->boolean('physical')->default(false);
            $table->boolean('original')->default(false);
            $table->boolean('sensitive')->default(false);
            $table->boolean('conditional')->default(false);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('request_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('observation')->nullable();
            $table->timestamp('subsanated_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['title_final_dossier_id', 'code'], 'title_final_dossier_requirement_unique');
        });
        Schema::create('title_final_dossier_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_final_dossier_id')->constrained()->restrictOnDelete();
            $table->string('action', 60);
            $table->text('description');
            $table->json('snapshot')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_final_dossier_events');
        Schema::dropIfExists('title_final_dossier_requirements');
        Schema::dropIfExists('title_final_dossiers');
        Schema::dropIfExists('professional_exam_attempts');
        Schema::dropIfExists('professional_exam_requirements');
        Schema::dropIfExists('professional_exam_profiles');
    }
};
