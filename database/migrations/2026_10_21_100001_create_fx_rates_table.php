<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history: one row per fetched or manually entered rate (D28).
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency_code', 3);
            // KES per one unit of the currency, before the display margin.
            $table->decimal('market_rate', 14, 6);
            $table->string('source', 16);
            $table->string('provider', 40)->nullable();
            // The provider's "as of" time for API rates; the entry time for manual ones.
            $table->timestamp('fetched_at');
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('override_until')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnUpdate();
            $table->index(['currency_code', 'source', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
