<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The client pays transaction costs. Defaults are Paystack's published Kenya rates
        // (M-Pesa 1.5%, local cards 2.9%); staff change them in Pricing settings.
        $this->migrator->add('pricing.mpesa_fee_bp', 150);
        $this->migrator->add('pricing.card_fee_bp', 290);
    }
};
