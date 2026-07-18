<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('checksum', 64);
            $table->json('summary');
            $table->timestamp('applied_at');
        });
        Schema::create('institutional_catalog_sync_records', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 80);
            $table->string('stable_key', 180);
            $table->unsignedBigInteger('entity_id');
            $table->string('checksum', 64);
            $table->json('managed_values');
            $table->timestamps();
            $table->unique(['entity_type', 'stable_key'], 'catalog_sync_entity_key_unique');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_catalog_sync_records');
        Schema::dropIfExists('institutional_catalog_versions');
    }
};
