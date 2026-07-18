<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_types', function (Blueprint $table) {
            $table->string('continuation_department', 200)->nullable()->after('responsible_area_id');
        });
        Schema::table('procedure_requests', function (Blueprint $table) {
            $table->string('academic_file_number', 100)->nullable()->after('tracking_code');
            $table->timestamp('academic_file_assigned_at')->nullable()->after('academic_file_number');
            $table->foreignId('academic_file_assigned_by')->nullable()->after('academic_file_assigned_at')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procedure_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_file_assigned_by');
            $table->dropColumn(['academic_file_number', 'academic_file_assigned_at']);
        });
        Schema::table('procedure_types', fn (Blueprint $table) => $table->dropColumn('continuation_department'));
    }
};
