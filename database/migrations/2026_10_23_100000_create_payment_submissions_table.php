<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A renter's payment of the reservation (M9): the method, their transaction message
        // and proof, then the reference and amount staff check it against. Money is KES minor units.
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method_code', 20);
            $table->text('transaction_message')->nullable();
            $table->foreignId('proof_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status', 20)->default('pending');

            $table->bigInteger('amount_minor')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('note', 1000)->nullable();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['booking_id', 'status']);
        });

        // What was actually paid. A partial payment leaves the rest on the balance (D38).
        Schema::table('bookings', function (Blueprint $table) {
            $table->bigInteger('reservation_paid_minor')->nullable()->after('security_deposit_minor');
            $table->timestamp('paid_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['reservation_paid_minor', 'paid_at']);
        });

        Schema::dropIfExists('payment_submissions');
    }
};
