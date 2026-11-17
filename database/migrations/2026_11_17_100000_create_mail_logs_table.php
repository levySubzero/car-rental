<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A copy of every email the app sent, whatever the mailer (the `log` mailer in dev too),
        // for the super admin's Email log. Bodies are encrypted; pruned by RetentionSettings.
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mailer', 40)->nullable();
            $table->string('to', 1000);
            $table->string('cc', 1000)->nullable();
            $table->string('subject', 500)->nullable();
            $table->string('notification', 255)->nullable();
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
