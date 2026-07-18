<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('procedure_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->string('tracking_code', 30)->unique();
            $table->string('subject', 200);
            $table->text('description');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'submitted_at']);
            $table->index(['status_id', 'submitted_at']);
            $table->index(['procedure_type_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_requests');
    }
};
