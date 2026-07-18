<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_observation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['procedure_request_id', 'submitted_at'], 'request_corrections_request_submitted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_corrections');
    }
};
