<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_attention_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['procedure_request_id', 'created_at'], 'attention_actions_request_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_attention_actions');
    }
};
