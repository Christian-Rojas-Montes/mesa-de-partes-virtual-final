<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->text('correction_instructions')->nullable();
            $table->timestamp('correction_deadline')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['procedure_request_id', 'created_at'], 'request_observations_request_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_observations');
    }
};
