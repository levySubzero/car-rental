<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.min_licence_years', 3);
        $this->migrator->add('booking.licence_must_outlive_return', true);
        $this->migrator->add('booking.require_flight_number_for_airport', false);
        $this->migrator->add('booking.max_additional_drivers', 1);
        $this->migrator->add('booking.flight_notice_days', 2);

        // PRD Q6: Kenyan residents give ID front and back, KRA PIN and certificate, and licence.
        // Passport holders give passport and licence; KRA is optional.
        $resident = ['documents' => ['id_front', 'id_back', 'kra_certificate', 'licence'], 'kra_pin' => 'required'];
        $passport = ['documents' => ['passport', 'licence'], 'kra_pin' => 'optional'];

        $this->migrator->add('booking.document_requirements', [
            'national_id' => ['resident' => $resident, 'non_resident' => $resident],
            'passport' => ['resident' => $passport, 'non_resident' => $passport],
        ]);

        $this->migrator->add('booking.deposit_payment_methods', []);
    }
};
