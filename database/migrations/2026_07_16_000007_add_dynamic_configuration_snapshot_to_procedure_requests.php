<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_requests', function (Blueprint $table) {
            $table->foreignId('procedure_variant_id')->nullable()->after('procedure_type_id')->constrained('procedure_variants')->restrictOnDelete();
            $table->json('dynamic_responses')->nullable()->after('description');
            $table->json('configuration_snapshot')->nullable()->after('dynamic_responses');
            $table->index(['procedure_variant_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('procedure_requests', function (Blueprint $table) {
            $table->dropIndex(['procedure_variant_id', 'submitted_at']);
            $table->dropConstrainedForeignId('procedure_variant_id');
            $table->dropColumn(['dynamic_responses', 'configuration_snapshot']);
        });
    }
};
