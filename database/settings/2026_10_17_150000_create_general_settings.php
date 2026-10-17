<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Contact details are filled from the CMS seed pack (M5.9); null means TODO.
        $this->migrator->add('general.company_name', 'Baines Executives');
        $this->migrator->add('general.legal_name', null);
        $this->migrator->add('general.phones', []);
        $this->migrator->add('general.whatsapp_number', null);
        $this->migrator->add('general.email', null);
        $this->migrator->add('general.address', null);
        $this->migrator->add('general.map_embed_url', null);
        $this->migrator->add('general.latitude', null);
        $this->migrator->add('general.longitude', null);
        $this->migrator->add('general.socials', []);
        $this->migrator->add('general.opening_hours', []);
        $this->migrator->add('general.whatsapp_templates', [
            'car_enquiry' => 'Hello Baines Executives, I would like to rent the {car} for {dates}.',
            'general_enquiry' => 'Hello Baines Executives, I have an enquiry.',
            'booking' => 'Hello Baines Executives, this is about my booking {reference}.',
        ]);
    }
};
