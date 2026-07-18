<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('description');
            $table->boolean('reception_open')->default(true)->after('unavailable_message');
            $table->boolean('allows_digital_registration')->default(true)->after('reception_open');
            $table->boolean('requires_physical_delivery')->default(false)->after('allows_digital_registration');
            $table->index(['reception_open', 'active'], 'procedure_types_reception_active_index');
        });

        Schema::table('procedure_variants', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('description');
            $table->boolean('reception_open')->default(true)->after('unavailable_message');
            $table->boolean('allows_digital_registration')->default(true)->after('reception_open');
            $table->boolean('requires_physical_delivery')->default(false)->after('allows_digital_registration');
        });
    }

    public function down(): void
    {
        Schema::table('procedure_variants', function (Blueprint $table) {
            $table->dropColumn(['conditions', 'reception_open', 'allows_digital_registration', 'requires_physical_delivery']);
        });
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->dropIndex('procedure_types_reception_active_index');
            $table->dropColumn(['instructions', 'reception_open', 'allows_digital_registration', 'requires_physical_delivery']);
        });
    }
};
