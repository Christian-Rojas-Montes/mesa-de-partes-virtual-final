<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_requirements', function (Blueprint $table) {
            $table->unique(['procedure_type_id', 'name'], 'procedure_requirements_type_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('procedure_requirements', function (Blueprint $table) {
            $table->dropUnique('procedure_requirements_type_name_unique');
        });
    }
};
