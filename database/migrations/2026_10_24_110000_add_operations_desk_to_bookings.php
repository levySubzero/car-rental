<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The operations desk (M11, D41). Money is KES minor units.
        Schema::table('bookings', function (Blueprint $table) {
            // Set on every end state; KYC retention counts from it. A legal hold stops the purge.
            $table->timestamp('closed_at')->nullable()->after('paid_at')->index();
            $table->boolean('legal_hold')->default(false)->after('closed_at');
            $table->timestamp('kyc_purged_at')->nullable()->after('legal_hold');

            // Staff check the address against the zone and quote the fee when the zone has none (D33).
            $table->bigInteger('delivery_fee_minor')->nullable()->after('delivery_contact_phone');
            $table->timestamp('delivery_checked_at')->nullable()->after('delivery_fee_minor');
            $table->foreignId('delivery_checked_by')->nullable()->after('delivery_checked_at')->constrained('users')->nullOnDelete();

            // Cancellation or no-show, and the refund paid back by hand (D34).
            $table->string('cancellation_reason', 1000)->nullable();
            $table->json('refund_tier')->nullable();
            $table->bigInteger('refund_suggested_minor')->nullable();
            $table->bigInteger('refund_minor')->nullable();
            $table->string('refund_override_reason', 1000)->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // What happened when the car went out and came back: one row of each type per booking.
        Schema::create('handover_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedInteger('odometer_km');
            $table->string('fuel_level', 20);
            $table->bigInteger('balance_received_minor')->nullable();
            $table->bigInteger('deposit_received_minor')->nullable();
            $table->bigInteger('deposit_refunded_minor')->nullable();
            $table->unsignedInteger('excess_km')->nullable();
            $table->bigInteger('extra_charges_minor')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_records');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_checked_by');
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropIndex(['closed_at']);
            $table->dropColumn([
                'closed_at', 'legal_hold', 'kyc_purged_at', 'delivery_fee_minor', 'delivery_checked_at',
                'cancellation_reason', 'refund_tier', 'refund_suggested_minor', 'refund_minor',
                'refund_override_reason', 'refund_reference', 'refunded_at',
            ]);
        });
    }
};
