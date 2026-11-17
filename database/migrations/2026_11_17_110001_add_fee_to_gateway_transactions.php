<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The transaction cost added on top of the reservation (2026-11). `amount_minor` is
        // what the gateway charged; the booking is credited `amount_minor - fee_minor`.
        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->bigInteger('fee_minor')->default(0)->after('amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->dropColumn('fee_minor');
        });
    }
};
