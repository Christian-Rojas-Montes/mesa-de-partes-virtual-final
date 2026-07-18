<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description');
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['procedure_type_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_requirements');
    }
};
