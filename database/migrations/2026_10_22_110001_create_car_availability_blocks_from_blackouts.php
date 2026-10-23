<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One table for every reason a car is unavailable (D35): booking holds, firm
        // bookings and staff blocks. Existing blackouts become `blocked` rows. The
        // (car_id, starts_at, ends_at) index keeps its old name: it backs the car foreign key.
        Schema::rename('car_blackouts', 'car_availability_blocks');

        Schema::table('car_availability_blocks', function (Blueprint $table) {
            $table->string('type', 20)->default('blocked')->after('car_id');
            $table->foreignId('booking_id')->nullable()->after('type')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable()->after('ends_at');
            $table->string('reason', 20)->nullable()->change();

            $table->index(['type', 'expires_at']);
        });

        DB::table('activity_log')->where('subject_type', 'car_blackout')->update(['subject_type' => 'availability_block']);
    }

    public function down(): void
    {
        DB::table('car_availability_blocks')->where('type', '!=', 'blocked')->delete();
        DB::table('activity_log')->where('subject_type', 'availability_block')->update(['subject_type' => 'car_blackout']);

        Schema::table('car_availability_blocks', function (Blueprint $table) {
            $table->dropIndex(['type', 'expires_at']);
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn(['type', 'expires_at']);
        });

        Schema::rename('car_availability_blocks', 'car_blackouts');
    }
};
