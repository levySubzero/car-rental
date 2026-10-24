<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Online payments (M11B, D42): one row per STK push or Paystack attempt, and one per
        // paybill (C2B) receipt, matched or not. Money is KES minor units.
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('external_ref', 100)->unique();
            $table->string('receipt', 100)->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->text('payer_phone')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('status', 20);
            $table->string('failure_reason', 500)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['booking_id', 'status']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->foreignId('gateway_transaction_id')->nullable()->after('proof_media_id')->constrained()->nullOnDelete();
        });

        // Staff switch a method back to manual without a deploy (M-Pesa and card only).
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('online_enabled')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('online_enabled');
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gateway_transaction_id');
        });

        Schema::dropIfExists('gateway_transactions');
    }
};
