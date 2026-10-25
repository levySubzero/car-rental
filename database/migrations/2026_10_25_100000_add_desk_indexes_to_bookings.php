<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| M12.6: the desk lists and today's pickups and returns filter by status and then range over
| or sort by the pickup or return time (BookingDeskService).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['status', 'pickup_at']);
            $table->index(['status', 'return_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'pickup_at']);
            $table->dropIndex(['status', 'return_at']);
        });
    }
};
