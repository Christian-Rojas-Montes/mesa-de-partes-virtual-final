<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->foreignId('responsible_area_id')->nullable()->after('presentation_modality_id')->constrained('areas')->restrictOnDelete();
            $table->index(['responsible_area_id', 'active'], 'procedure_types_responsible_area_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->dropIndex('procedure_types_responsible_area_active_index');
            $table->dropConstrainedForeignId('responsible_area_id');
        });
    }
};
