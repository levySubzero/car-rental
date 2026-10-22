<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('home.hero_media_uuid', null);
        $this->migrator->add('home.headline', 'Executive car rental and safaris in Nairobi');
        $this->migrator->add('home.subline', 'Executive cars, chauffeurs and safaris, delivered to your door with 24/7 support.');
        $this->migrator->add('home.primary_cta_label', 'Rent a Car');
        $this->migrator->add('home.secondary_cta_label', 'Enquire');
        $this->migrator->add('home.featured_car_count', 6);
        $this->migrator->add('home.marquee_position', 'page_bottom');
        $this->migrator->add('home.sections', array_map(
            fn (string $key) => ['key' => $key, 'visible' => true],
            ['about', 'featured_fleet', 'car_types', 'services', 'policy_highlights', 'requirements', 'why_choose_us', 'trust_strip', 'contact_cta'],
        ));
        // Client information sheet (Oct 2026).
        $this->migrator->add('home.trust_figures', [
            ['value' => '543+', 'label' => 'Clients served'],
            ['value' => '35', 'label' => 'Safaris completed'],
            ['value' => '24/7', 'label' => 'WhatsApp support'],
            ['value' => '30 km', 'label' => 'Delivery radius'],
        ]);
    }
};
