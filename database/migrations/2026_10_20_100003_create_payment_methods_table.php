<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A fixed set of rows keyed by PaymentMethodCode (D25), created by the seed pack.
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->text('instructions');
            $table->boolean('requires_transaction_message')->default(true);
            $table->boolean('requires_proof')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // Paybill, till, bank details, card link (D20).
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
