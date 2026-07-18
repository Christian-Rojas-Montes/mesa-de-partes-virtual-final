<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->foreignId('procedure_category_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('presentation_modality_id')->nullable()->after('procedure_category_id')->constrained()->restrictOnDelete();
            $table->dateTime('available_from')->nullable()->after('attention_days');
            $table->dateTime('available_until')->nullable()->after('available_from');
            $table->string('academic_period', 50)->nullable()->after('available_until');
            $table->text('unavailable_message')->nullable()->after('academic_period');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('unavailable_message');
            $table->boolean('requires_payment')->default(false)->after('sort_order');
            $table->decimal('amount', 12, 2)->nullable()->after('requires_payment');
            $table->char('currency', 3)->default('PEN')->after('amount');
            $table->string('payment_concept', 200)->nullable()->after('currency');
            $table->string('payment_timing', 20)->nullable()->after('payment_concept');
            $table->text('payment_observation')->nullable()->after('payment_timing');
            $table->index(['procedure_category_id', 'active', 'sort_order'], 'procedure_types_category_active_sort_index');
            $table->index(['available_from', 'available_until'], 'procedure_types_availability_index');
        });

        Schema::create('procedure_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('presentation_modality_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
            $table->string('academic_period', 50)->nullable();
            $table->text('unavailable_message')->nullable();
            $table->unsignedSmallInteger('attention_days')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('requires_payment')->default(false);
            $table->decimal('amount', 12, 2)->nullable();
            $table->char('currency', 3)->default('PEN');
            $table->string('payment_concept', 200)->nullable();
            $table->string('payment_timing', 20)->nullable();
            $table->text('payment_observation')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['procedure_type_id', 'code'], 'procedure_variants_type_code_unique');
            $table->unique(['procedure_type_id', 'name'], 'procedure_variants_type_name_unique');
            $table->index(['procedure_type_id', 'active', 'sort_order'], 'procedure_variants_type_active_sort_index');
            $table->index(['available_from', 'available_until'], 'procedure_variants_availability_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_variants');
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->dropIndex('procedure_types_availability_index');
            $table->dropIndex('procedure_types_category_active_sort_index');
            $table->dropConstrainedForeignId('presentation_modality_id');
            $table->dropConstrainedForeignId('procedure_category_id');
            $table->dropColumn(['available_from', 'available_until', 'academic_period', 'unavailable_message', 'sort_order', 'requires_payment', 'amount', 'currency', 'payment_concept', 'payment_timing', 'payment_observation']);
        });
    }
};
