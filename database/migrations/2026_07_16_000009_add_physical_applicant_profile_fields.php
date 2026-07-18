<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('student_code', 50)->nullable()->unique()->after('document_number');
            $table->string('academic_program', 150)->nullable()->after('phone');
            $table->string('academic_condition', 100)->nullable()->after('academic_program');
            $table->boolean('account_claim_pending')->default(false)->after('academic_condition')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_claim_pending']);
            $table->dropUnique(['student_code']);
            $table->dropColumn(['student_code', 'academic_program', 'academic_condition', 'account_claim_pending']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
