<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 50);
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['procedure_request_id', 'created_at'], 'request_histories_request_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_histories');
    }
};
