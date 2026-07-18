<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_requirements', function (Blueprint $table) {
            $table->foreignId('procedure_variant_id')->nullable()->after('procedure_type_id')->constrained()->restrictOnDelete();
            $table->string('type', 40)->default('digital_file')->after('description');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('required');
            $table->boolean('requires_original')->default(false)->after('active');
            $table->boolean('requires_simple_copy')->default(false)->after('requires_original');
            $table->boolean('requires_authenticated_copy')->default(false)->after('requires_simple_copy');
            $table->boolean('requires_legalized_copy')->default(false)->after('requires_authenticated_copy');
            $table->boolean('requires_endorsement')->default(false)->after('requires_legalized_copy');
            $table->unsignedSmallInteger('copy_count')->default(1)->after('requires_endorsement');
            $table->json('allowed_formats')->nullable()->after('copy_count');
            $table->unsignedInteger('max_file_size_kb')->nullable()->after('allowed_formats');
            $table->boolean('requires_issue_date')->default(false)->after('max_file_size_kb');
            $table->unsignedSmallInteger('validity_value')->nullable()->after('requires_issue_date');
            $table->string('validity_unit', 20)->nullable()->after('validity_value');
            $table->boolean('requires_physical_submission')->default(false)->after('validity_unit');
            $table->boolean('requires_digital_file')->default(true)->after('requires_physical_submission');
            $table->boolean('sensitive')->default(false)->after('requires_digital_file');
            $table->text('observations')->nullable()->after('sensitive');
            $table->json('conditions')->nullable()->after('observations');
            $table->index(['procedure_variant_id', 'active', 'sort_order'], 'procedure_requirements_variant_active_sort_index');
            $table->index(['type', 'active'], 'procedure_requirements_type_active_index');
            $table->index(['sensitive', 'active'], 'procedure_requirements_sensitive_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('procedure_requirements', function (Blueprint $table) {
            $table->dropIndex('procedure_requirements_sensitive_active_index');
            $table->dropIndex('procedure_requirements_type_active_index');
            $table->dropIndex('procedure_requirements_variant_active_sort_index');
            $table->dropConstrainedForeignId('procedure_variant_id');
            $table->dropColumn(['type', 'sort_order', 'requires_original', 'requires_simple_copy', 'requires_authenticated_copy', 'requires_legalized_copy', 'requires_endorsement', 'copy_count', 'allowed_formats', 'max_file_size_kb', 'requires_issue_date', 'validity_value', 'validity_unit', 'requires_physical_submission', 'requires_digital_file', 'sensitive', 'observations', 'conditions']);
        });
    }
};
