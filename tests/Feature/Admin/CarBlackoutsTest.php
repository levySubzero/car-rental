<?php

use App\Enums\Permission;
use App\Models\AvailabilityBlock;
use App\Models\Car;

beforeEach(function () {
    $this->car = Car::factory()->create();
    $this->ops = staffWith([Permission::CarsView, Permission::AvailabilityManage]);
});

it('adds a blackout with its author and logs it', function () {
    $this->actingAs($this->ops)
        ->post(route('admin.cars.blackouts.store', $this->car), [
            'starts_at' => '2026-11-02T06:00:00.000Z',
            'ends_at' => '2026-11-04T15:00:00.000Z',
            'reason' => 'maintenance',
            'notes' => 'Annual service',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $blackout = $this->car->blackouts()->sole();

    expect($blackout->created_by)->toBe($this->ops->id)
        ->and($blackout->starts_at->toIso8601String())->toBe('2026-11-02T06:00:00+00:00');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'availability', 'subject_id' => $blackout->id, 'event' => 'created']);
});

it('validates blackout input', function () {
    $this->actingAs($this->ops)
        ->post(route('admin.cars.blackouts.store', $this->car), [
            'starts_at' => '2026-11-04 10:00',
            'ends_at' => '2026-11-04 09:00',
            'reason' => 'holiday',
        ])
        ->assertSessionHasErrors(['ends_at', 'reason']);
});

it('refuses overlapping blackouts on the same car but allows back-to-back ones', function () {
    AvailabilityBlock::factory()->for($this->car)->create(['starts_at' => '2026-11-02 08:00', 'ends_at' => '2026-11-04 08:00']);
    $add = fn (string $from, string $until) => $this->actingAs($this->ops)->post(
        route('admin.cars.blackouts.store', $this->car),
        ['starts_at' => $from, 'ends_at' => $until, 'reason' => 'private_use'],
    );

    $add('2026-11-03 08:00', '2026-11-05 08:00');
    expect($this->car->blackouts()->count())->toBe(1);

    $add('2026-11-04 08:00', '2026-11-05 08:00')->assertSessionHasNoErrors();
    expect($this->car->blackouts()->count())->toBe(2);

    AvailabilityBlock::factory()->create(['starts_at' => '2026-11-03 08:00', 'ends_at' => '2026-11-05 08:00']);
    expect(AvailabilityBlock::count())->toBe(3);
});

it('updates a blackout without clashing with itself, and removes it', function () {
    $blackout = AvailabilityBlock::factory()->for($this->car)->create(['starts_at' => '2026-11-02 08:00', 'ends_at' => '2026-11-04 08:00']);

    $this->actingAs($this->ops)
        ->put(route('admin.cars.blackouts.update', [$this->car, $blackout]), [
            'starts_at' => '2026-11-02 08:00', 'ends_at' => '2026-11-06 08:00', 'reason' => 'maintenance',
        ])
        ->assertSessionHasNoErrors();

    expect($blackout->fresh()->ends_at->toDateString())->toBe('2026-11-06');

    $this->actingAs($this->ops)->delete(route('admin.cars.blackouts.destroy', [$this->car, $blackout]))->assertRedirect();
    expect(AvailabilityBlock::find($blackout->id))->toBeNull();
});

it('only finds blackouts through their own car', function () {
    $other = AvailabilityBlock::factory()->create();

    $this->actingAs($this->ops)
        ->delete(route('admin.cars.blackouts.destroy', [$this->car, $other]))
        ->assertNotFound();
});

it('needs availability.manage', function () {
    $blackout = AvailabilityBlock::factory()->for($this->car)->create();
    $editor = staffWith([Permission::CarsView, Permission::CarsUpdate]);

    $this->actingAs($editor)
        ->post(route('admin.cars.blackouts.store', $this->car), ['starts_at' => '2026-11-02 08:00', 'ends_at' => '2026-11-03 08:00', 'reason' => 'maintenance'])
        ->assertForbidden();
    $this->actingAs($editor)->delete(route('admin.cars.blackouts.destroy', [$this->car, $blackout]))->assertForbidden();
});
