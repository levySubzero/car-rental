<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The renter mails when staff accept or decline a request.
        // The stored payload decodes as an object, so it is turned back into an array first.
        $this->migrator->update('notification.channels', fn (array|object $channels) => json_decode((string) json_encode($channels), true) + [
            'booking_accepted' => ['mail' => true, 'whatsapp' => false],
            'booking_cancelled' => ['mail' => true, 'whatsapp' => false],
        ]);
    }
};
