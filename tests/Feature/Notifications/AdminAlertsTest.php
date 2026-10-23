<?php

use App\Enums\AlertKind;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\User;
use App\Services\Notifications\AdminAlerts;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->alerts = app(AdminAlerts::class);
});

function alertFor(User $user, int $count = 1): void
{
    foreach (range(1, $count) as $i) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AlertKind::NewBooking->value,
            'data' => ['title' => "Alert {$i}", 'body' => 'Body', 'url' => route('admin.payments.index')],
        ]);
    }
}

it('sends an alert to staff holding its permissions and to super admins only', function () {
    $payments = staffWith([Permission::PaymentsView]);
    $viaRole = staffWithRole('operations-like');
    $viaRole->roles()->first()->givePermissionTo(Permission::PaymentsView);
    $admin = superAdmin();
    $other = staffWith([Permission::CarsView]);
    $inactive = staffWith([Permission::PaymentsView]);
    $inactive->forceFill(['is_active' => false])->save();

    $this->alerts->send(AlertKind::PaymentSubmitted, 'Payment to check', 'Body', route('admin.payments.index'));

    expect($payments->notifications()->count())->toBe(1)
        ->and($viaRole->notifications()->count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1)
        ->and($other->notifications()->count())->toBe(0)
        ->and($inactive->notifications()->count())->toBe(0)
        ->and($payments->notifications()->first()->type)->toBe('payment_submitted');
});

it('alerts staff about new bookings and payments', function () {
    $staff = staffWith([Permission::PaymentsView]);
    $booking = Booking::factory()->awaitingPayment()->create();
    BookingDriver::factory()->for($booking)->create(['first_name' => 'Amina', 'surname' => 'Otieno']);

    app(BookingNotifier::class)->submitted($booking, str_repeat('a', 64));

    $alert = $staff->notifications()->sole();
    expect($alert->data['title'])->toBe("New booking {$booking->reference}")
        ->and($alert->data['body'])->toStartWith('Amina Otieno, ')
        ->and($alert->data['url'])->toBe(route('admin.payments.index', ['search' => $booking->reference]));
});

it('shares the unread count and the latest alerts on admin pages', function () {
    $staff = staffWith();
    alertFor($staff, 12);
    $staff->notifications()->first()->markAsRead();

    $this->actingAs($staff)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.unread', 11)
            ->has('alerts.latest', AdminAlerts::LATEST)
            ->where('alerts.latest.0.icon', 'calendar-plus'));
});

it('opens an alert, marking it read, and marks all read', function () {
    $staff = staffWith();
    alertFor($staff, 2);
    $first = $staff->notifications()->first();

    $this->actingAs($staff)->post(route('admin.alerts.open', $first->id))
        ->assertRedirect(route('admin.payments.index'));
    expect($first->fresh()->read_at)->not->toBeNull()
        ->and($staff->unreadNotifications()->count())->toBe(1);

    $this->actingAs($staff)->post(route('admin.alerts.read-all'))->assertRedirect();
    expect($staff->unreadNotifications()->count())->toBe(0);
});

it('never opens or clears another staff member\'s alerts', function () {
    $owner = staffWith();
    alertFor($owner);
    $alert = $owner->notifications()->sole();

    $this->actingAs(staffWith())->post(route('admin.alerts.open', $alert->id))->assertNotFound();
    $this->actingAs(staffWith())->post(route('admin.alerts.read-all'));

    expect($alert->fresh()->read_at)->toBeNull();
});

it('deletes read alerts after 90 days', function () {
    $staff = staffWith();
    alertFor($staff, 3);
    [$old, $oldUnread] = $staff->notifications()->limit(2)->get()->all();
    $old->forceFill(['created_at' => now()->subDays(91), 'read_at' => now()])->save();
    $oldUnread->forceFill(['created_at' => now()->subDays(91)])->save();

    $this->artisan('alerts:prune')->assertSuccessful();

    expect(DatabaseNotification::query()->pluck('id')->all())->not->toContain($old->id)
        ->and(DatabaseNotification::query()->count())->toBe(2);
});
