<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The renter portal (M10B) finds bookings by the main driver's email or phone.
        Schema::table('booking_drivers', function (Blueprint $table) {
            $table->string('email_normalized')->default('')->after('email')->index();
            $table->string('phone_digits', 30)->default('')->after('phone')->index();
        });

        DB::table('booking_drivers')->select(['id', 'email', 'phone'])->orderBy('id')->each(function (object $driver) {
            DB::table('booking_drivers')->where('id', $driver->id)->update([
                'email_normalized' => mb_strtolower(trim((string) $driver->email)),
                'phone_digits' => PhoneNumber::digits((string) $driver->phone),
            ]);
        });

        // A renter's request from the portal: cancel or change. Staff handle it by hand.
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->string('staff_note', 1000)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');

        Schema::table('booking_drivers', function (Blueprint $table) {
            $table->dropIndex(['email_normalized']);
            $table->dropIndex(['phone_digits']);
            $table->dropColumn(['email_normalized', 'phone_digits']);
        });
    }
};
