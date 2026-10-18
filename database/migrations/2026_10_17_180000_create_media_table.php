<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TECHNICAL_PLAN §6.3, with status and processing split (D18).
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Null while pending, and for standalone collections such as site.hero.
            $table->nullableMorphs('mediable');
            $table->string('collection', 100);

            $table->string('disk', 64);
            $table->string('path');
            $table->json('conversions')->nullable();

            $table->string('original_name');
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('visibility', 16);
            $table->string('status', 16);
            $table->string('processing', 16);

            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();

            $table->nullableMorphs('uploaded_by');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mediable_type', 'mediable_id', 'collection', 'sort_order'], 'media_owner_collection_index');
            $table->index(['status', 'expires_at']);
            $table->index(['collection', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
