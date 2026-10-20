<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Written by the public contact form (M7.15).
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('message');
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preferred_dates', 120)->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
