<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_derivations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_area_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->foreignId('to_area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('derived_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['procedure_request_id', 'derived_at'], 'request_derivations_request_derived_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_derivations');
    }
};
