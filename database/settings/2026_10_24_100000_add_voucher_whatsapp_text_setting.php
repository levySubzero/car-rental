<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add(
            'notification.voucher_whatsapp_text',
            'Hello from Baines Executives. Your booking {reference} for the {car} on {dates} is confirmed. Your voucher has been sent to your email.',
        );
    }
};
