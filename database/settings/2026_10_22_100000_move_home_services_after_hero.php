<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Home page flow (M7): the service overview comes straight after the hero, then the fleet,
 * then trust and practical details. Only an order nobody has changed is moved; an order set
 * in Home settings, including hidden sections, is kept.
 */
return new class extends SettingsMigration
{
    private const OLD = ['about', 'featured_fleet', 'car_types', 'services', 'policy_highlights', 'requirements', 'why_choose_us', 'trust_strip', 'contact_cta'];

    private const NEW = ['services', 'featured_fleet', 'car_types', 'why_choose_us', 'trust_strip', 'requirements', 'about', 'policy_highlights', 'contact_cta'];

    public function up(): void
    {
        $this->migrator->update('home.sections', fn (array $sections) => $this->reorder($sections, self::OLD, self::NEW));
    }

    public function down(): void
    {
        $this->migrator->update('home.sections', fn (array $sections) => $this->reorder($sections, self::NEW, self::OLD));
    }

    /**
     * @param  array<int, array{key: string, visible: bool}>  $sections  as stored
     * @param  list<string>  $from
     * @param  list<string>  $to
     * @return list<array{key: string, visible: bool}>
     */
    private function reorder(array $sections, array $from, array $to): array
    {
        $untouched = array_column($sections, 'key') === $from
            && ! in_array(false, array_column($sections, 'visible'), true);

        return $untouched
            ? array_map(fn (string $key) => ['key' => $key, 'visible' => true], $to)
            : array_values($sections);
    }
};
