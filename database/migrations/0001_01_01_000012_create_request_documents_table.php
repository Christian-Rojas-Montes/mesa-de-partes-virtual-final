<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedure_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procedure_requirement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('disk', 30);
            $table->string('path', 500)->unique();
            $table->string('stored_name', 100);
            $table->string('extension', 10);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamps();

            $table->index(['procedure_request_id', 'procedure_requirement_id'], 'request_documents_request_requirement_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_documents');
    }
};
