<?php

use App\Enums\DayCountingStrategy;
use App\Enums\MarqueePosition;
use App\Enums\RoundingMode;
use App\Settings\BookingSettings;
use App\Settings\FxSettings;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;
use App\Settings\NotificationSettings;
use App\Settings\PricingSettings;
use App\Settings\RetentionSettings;
use App\Settings\SecuritySettings;

it('loads every settings group with the TECHNICAL_PLAN §9 defaults', function () {
    $pricing = app(PricingSettings::class);

    expect($pricing->reservation_percent_bp)->toBe(2500)
        ->and($pricing->reservation_rounding)->toBe(RoundingMode::CeilMajor)
        ->and($pricing->day_counting)->toBe(DayCountingStrategy::CalendarInclusive)
        ->and($pricing->hold_minutes)->toBe(120)
        ->and($pricing->default_mileage_km)->toBe(300)
        ->and($pricing->unlimited_mileage_after_days)->toBeNull()
        ->and(app(FxSettings::class)->margin_minor)->toBe(300)
        ->and(app(BookingSettings::class)->document_requirements['passport']['non_resident']['kra_pin'])->toBe('optional')
        ->and(app(HomeSettings::class)->marquee_position)->toBe(MarqueePosition::PageBottom)
        ->and(app(RetentionSettings::class)->kyc_retention_months)->toBe(12)
        ->and(app(NotificationSettings::class)->channels)->toHaveKey('booking_confirmed')
        ->and(app(GeneralSettings::class)->company_name)->toBe('Baines Executives')
        ->and(app(SecuritySettings::class)->require_two_factor)->toBeFalse();
});

it('persists changes', function () {
    $settings = app(PricingSettings::class);
    $settings->day_counting = DayCountingStrategy::DaytimeBlocks;
    $settings->save();

    app()->forgetInstance(PricingSettings::class);

    expect(app(PricingSettings::class)->day_counting)->toBe(DayCountingStrategy::DaytimeBlocks);
});
