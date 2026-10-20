<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money is in minor units (D6). Specs the site filters or shows as first-class fields are
 * columns; anything informal goes in `metadata` (D20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('car_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('engine_type', 80)->nullable();
            $table->decimal('engine_capacity_litres', 3, 1)->nullable(); // 2.5 (L)
            $table->string('fuel_type', 20);
            $table->string('transmission', 20);
            $table->string('colour', 40)->nullable();
            $table->unsignedTinyInteger('doors');
            $table->unsignedTinyInteger('seats');
            $table->unsignedBigInteger('daily_rate_minor');
            $table->unsignedBigInteger('car_value_minor')->nullable();
            $table->unsignedInteger('mileage_limit_km')->nullable(); // null: PricingSettings::default_mileage_km
            $table->unsignedBigInteger('damage_excess_minor')->nullable(); // null: not shown
            $table->char('damage_excess_currency', 3)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'is_available']);
            $table->index('daily_rate_minor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
