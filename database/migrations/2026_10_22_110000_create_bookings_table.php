<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A renter's booking, from draft to completion (M8). Money is KES minor units; the
        // quote snapshot holds the lines, the FX table and the settings used (D37).
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->char('access_token_hash', 64)->unique();
            $table->foreignId('car_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');

            $table->timestamp('pickup_at');
            $table->timestamp('return_at');
            $table->string('pickup_mode', 20)->default('office');
            $table->string('return_mode', 20)->default('office');
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();

            // Home delivery and collection (D33): free text, checked by staff.
            foreach (['delivery', 'return'] as $leg) {
                $table->string("{$leg}_address", 500)->nullable();
                $table->string("{$leg}_landmark", 255)->nullable();
                $table->decimal("{$leg}_lat", 9, 6)->nullable();
                $table->decimal("{$leg}_lng", 9, 6)->nullable();
                $table->string("{$leg}_contact_phone", 30)->nullable();
            }

            $table->json('extras')->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->char('display_currency', 3)->nullable();

            $table->json('quote')->nullable();
            $table->json('cancellation_tiers')->nullable();
            $table->bigInteger('total_minor')->nullable();
            $table->bigInteger('reservation_minor')->nullable();
            $table->bigInteger('balance_minor')->nullable();
            $table->bigInteger('security_deposit_minor')->nullable();

            $table->foreignId('policy_bundle_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('accepted_terms_at')->nullable();
            $table->string('accepted_terms_ip', 45)->nullable();

            $table->string('current_step', 20)->default('trip');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['status', 'created_at']);
            $table->index(['car_id', 'pickup_at']);
        });

        // The main driver only (D37). Kept apart from the booking so KYC can be purged.
        Schema::create('booking_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 80);
            $table->string('second_name', 80)->nullable();
            $table->string('surname', 80);
            $table->date('dob');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('nationality', 80);
            $table->boolean('is_kenyan_resident');
            $table->string('id_type', 20);
            $table->text('id_number');
            $table->char('id_number_hash', 64)->index();
            $table->text('kra_pin')->nullable();
            $table->text('licence_number');
            $table->date('licence_expiry');
            $table->unsignedTinyInteger('licence_years_declared');
            $table->timestamps();
        });

        // One counter per year for booking references, read under a row lock on submit.
        Schema::create('booking_reference_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reference_sequences');
        Schema::dropIfExists('booking_drivers');
        Schema::dropIfExists('bookings');
    }
};
