<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per preset page in App\Support\Content\PageRegistry (D22).
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->json('content');
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
