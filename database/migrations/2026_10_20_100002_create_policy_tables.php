<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->string('group', 20);
            $table->string('summary', 500)->nullable();
            $table->boolean('show_on_home')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Published versions are immutable; a policy has at most one draft (published_at null).
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->timestamp('published_at')->nullable();
            // Null means the seed pack wrote it (CmsSeeder).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['policy_id', 'version']);
        });

        // The set of published versions a renter accepts (D24); bookings reference it from M8.
        Schema::create('policy_bundles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->timestamp('published_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('policy_bundle_version', function (Blueprint $table) {
            $table->foreignId('policy_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_version_id')->constrained()->restrictOnDelete();

            $table->primary(['policy_bundle_id', 'policy_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_bundle_version');
        Schema::dropIfExists('policy_bundles');
        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('policies');
    }
};
