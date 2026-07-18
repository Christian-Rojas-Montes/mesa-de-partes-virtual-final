<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('request_appointments')->restrictOnDelete();
            $table->date('appointment_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('office', 200);
            $table->foreignId('area_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->string('reference_person', 150)->nullable();
            $table->string('reason', 500);
            $table->text('instructions')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status', 30);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['procedure_request_id', 'appointment_date', 'status'], 'request_appointments_request_date_status_index');
        });
        Schema::create('request_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('available_at');
            $table->string('office', 200);
            $table->string('pickup_requirement', 500)->nullable();
            $table->foreignId('marked_ready_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('received_by_name', 200)->nullable();
            $table->boolean('identity_document_verified')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->text('observation')->nullable();
            $table->string('status', 30)->default('ready');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_pickups');
        Schema::dropIfExists('request_appointments');
    }
};
