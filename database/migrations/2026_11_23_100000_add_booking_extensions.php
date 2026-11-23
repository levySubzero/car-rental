<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extensions as bookings of their own (D50, 2026-11): each one points at the booking
        // the hire started with, and that root carries where the whole chain now ends.
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('root_booking_id')->nullable()->after('id')->constrained('bookings')->restrictOnDelete();
            $table->timestamp('extended_until')->nullable()->after('return_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('root_booking_id');
            $table->dropColumn('extended_until');
        });
    }
};
