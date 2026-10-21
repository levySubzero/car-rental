<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('basis', 16);
            // Null for info-only and per-km charges quoted on request.
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency_code', 3);
            $table->boolean('tax_inclusive')->default(true);
            $table->string('payable_at', 16);
            // ChargeApplies: picked by the renter, on every booking, or listed only (D32).
            $table->string('applies', 16)->default('extra');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnUpdate();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->text('description')->nullable();
            // Null means staff quote the fee (D29).
            $table->unsignedBigInteger('fee_minor')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('deposit_bands', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('min_age');
            $table->unsignedTinyInteger('max_age');
            // Of the car value: 200 = 2%.
            $table->unsignedSmallInteger('percent_bp');
            $table->string('label', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cancellation_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('hours_before_pickup')->unique();
            $table->unsignedSmallInteger('refund_percent_bp');
            $table->string('label', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_tiers');
        Schema::dropIfExists('deposit_bands');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('charges');
    }
};
