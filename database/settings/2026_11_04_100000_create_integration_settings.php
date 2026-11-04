<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Saved on the admin Integrations page (M12B.15). Empty means every key comes from env.
        foreach (['daraja', 'paystack', 'resend', 'fx', 'turnstile', 's3'] as $integration) {
            $this->migrator->addEncrypted("integrations.{$integration}", []);
        }
    }
};
