<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_dynamic_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('procedure_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('key', 80);
            $table->string('type', 30);
            $table->string('label', 150);
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('min_length')->nullable();
            $table->unsignedSmallInteger('max_length')->nullable();
            $table->decimal('min_value', 14, 4)->nullable();
            $table->decimal('max_value', 14, 4)->nullable();
            $table->json('options')->nullable();
            $table->string('validation_rule', 500)->nullable();
            $table->json('visibility_conditions')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['procedure_type_id', 'procedure_variant_id', 'key'], 'procedure_fields_scope_key_unique');
            $table->index(['procedure_type_id', 'active', 'sort_order'], 'procedure_fields_type_active_sort_index');
            $table->index(['procedure_variant_id', 'active', 'sort_order'], 'procedure_fields_variant_active_sort_index');
        });

        Schema::create('procedure_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('required_procedure_type_id')->nullable()->constrained('procedure_types')->restrictOnDelete();
            $table->string('type', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['procedure_type_id', 'active', 'sort_order'], 'procedure_prerequisites_type_active_sort_index');
            $table->index(['required_procedure_type_id', 'active'], 'procedure_prerequisites_required_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_prerequisites');
        Schema::dropIfExists('procedure_dynamic_fields');
    }
};
