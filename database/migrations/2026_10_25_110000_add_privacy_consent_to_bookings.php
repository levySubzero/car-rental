<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| M12.13: the renter's consent to the privacy notice (Data Protection Act 2019), given with
| the rental terms at submit. Bookings submitted before this have none recorded.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('accepted_privacy_at')->nullable()->after('accepted_terms_ip');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('accepted_privacy_at');
        });
    }
};
