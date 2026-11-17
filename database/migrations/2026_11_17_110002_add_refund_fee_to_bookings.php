<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transaction costs taken off a refund (2026-11): `refund_minor` is what goes back to
        // the client, after `refund_fee_minor`.
        Schema::table('bookings', function (Blueprint $table) {
            $table->bigInteger('refund_fee_minor')->nullable()->after('refund_minor');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('refund_fee_minor');
        });
    }
};
