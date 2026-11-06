<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('source', 20)->default('website')->after('status');
        });

        // A client staff enter by hand may give little more than a name and a phone number.
        Schema::table('booking_drivers', function (Blueprint $table) {
            $table->date('dob')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('phone', 30)->nullable()->change();
            $table->string('nationality', 80)->nullable()->change();
            $table->string('id_type', 20)->nullable()->change();
            $table->text('id_number')->nullable()->change();
            $table->char('id_number_hash', 64)->nullable()->change();
            $table->text('licence_number')->nullable()->change();
            $table->date('licence_expiry')->nullable()->change();
            $table->unsignedTinyInteger('licence_years_declared')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
