<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notification.staff_emails', []);
        $this->migrator->add('notification.staff_whatsapp_number', null);
        $this->migrator->add('notification.channels', [
            'booking_received' => ['mail' => true, 'whatsapp' => false],
            'staff_new_booking' => ['mail' => true, 'whatsapp' => true],
            'payment_submitted' => ['mail' => true, 'whatsapp' => true],
            'booking_confirmed' => ['mail' => true, 'whatsapp' => true],
            'payment_rejected' => ['mail' => true, 'whatsapp' => false],
        ]);
        $this->migrator->add('notification.voucher_email_subject', 'Your Baines Executives booking {reference} is confirmed');
        $this->migrator->add('notification.voucher_email_intro', 'Thank you for choosing Baines Executives. Your reservation is confirmed and your voucher is attached.');
    }
};
