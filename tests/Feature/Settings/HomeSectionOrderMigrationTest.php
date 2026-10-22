<?php

use App\Settings\HomeSettings;

const OLD_HOME_ORDER = ['about', 'featured_fleet', 'car_types', 'services', 'policy_highlights', 'requirements', 'why_choose_us', 'trust_strip', 'contact_cta'];

function runHomeOrderMigration(array $keys, array $hidden = []): array
{
    $home = app(HomeSettings::class);
    $home->sections = array_map(fn (string $key) => ['key' => $key, 'visible' => ! in_array($key, $hidden, true)], $keys);
    $home->save();

    (require database_path('settings/2026_10_22_100000_move_home_services_after_hero.php'))->up();

    return array_column(app(HomeSettings::class)->refresh()->sections, 'key');
}

it('moves the services overview after the hero when the order was never changed', function () {
    expect(runHomeOrderMigration(OLD_HOME_ORDER))
        ->toBe(['services', 'featured_fleet', 'car_types', 'why_choose_us', 'trust_strip', 'requirements', 'about', 'policy_highlights', 'contact_cta']);
});

it('keeps an order or hidden sections staff have set', function () {
    $custom = ['contact_cta', ...array_slice(OLD_HOME_ORDER, 0, 8)];

    expect(runHomeOrderMigration($custom))->toBe($custom)
        ->and(runHomeOrderMigration(OLD_HOME_ORDER, hidden: ['about']))->toBe(OLD_HOME_ORDER);
});
