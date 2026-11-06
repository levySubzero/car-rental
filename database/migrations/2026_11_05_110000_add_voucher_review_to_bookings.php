<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Staff's wording on the voucher, kept apart from the booking's own figures.
            $table->json('voucher_overrides')->nullable()->after('quote');
            $table->timestamp('voucher_sent_at')->nullable()->after('paid_at');
        });

        // Until now the voucher went out by itself as soon as a booking was paid.
        DB::table('bookings')->whereNotNull('paid_at')->update(['voucher_sent_at' => DB::raw('paid_at')]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['voucher_overrides', 'voucher_sent_at']);
        });
    }
};
