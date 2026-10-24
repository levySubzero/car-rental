<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The renter mail when staff set or correct the delivery fee (M11.13).
        // The stored payload decodes as an object, so it is turned back into an array first.
        $this->migrator->update('notification.channels', fn (array|object $channels) => json_decode((string) json_encode($channels), true) + [
            'delivery_updated' => ['mail' => true, 'whatsapp' => false],
        ]);
    }
};
