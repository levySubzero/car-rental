<?php

use App\Enums\ContactMessageStatus;
use App\Models\Car;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\NewContactMessage;
use App\Services\Media\MediaService;
use App\Settings\NotificationSettings;
use App\Support\Rules\Turnstile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    CatalogFixtures::setUp();
    config(['services.turnstile.secret_key' => Turnstile::TEST_SECRET]);
    RateLimiter::clear('contact');
    $staff = User::factory()->staff()->create();
    $this->car = Car::factory()->published()->create(['name' => 'Toyota Prado TX']);
    app(MediaService::class)->sync($this->car, 'car.gallery', [CatalogFixtures::photo($staff)->uuid]);
});

it('shows the form with the car chosen from a "Book this car" link', function () {
    Car::factory()->create(['name' => 'Draft car']);

    $this->get("/contact?car={$this->car->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/contact')
            ->where('selected_car', $this->car->slug)
            ->has('cars', 1)
            ->where('sent', false));

    $this->get('/contact?car=not-a-car')->assertInertia(fn (Assert $page) => $page->where('selected_car', null));
});

it('stores a message, tells the staff emails and confirms', function () {
    Notification::fake();
    $settings = app(NotificationSettings::class);
    $settings->staff_emails = ['bookings@baines.test'];
    $settings->save();

    $this->post('/contact', [
        'name' => ' Amina ',
        'phone' => '+254 712 345 678',
        'car' => $this->car->slug,
        'preferred_dates' => '3 to 6 November',
        'message' => 'Is the Prado free for a Mara trip?',
    ])->assertRedirect('/contact');

    $message = ContactMessage::sole();

    expect($message->name)->toBe('Amina')
        ->and($message->email)->toBeNull()
        ->and($message->car_id)->toBe($this->car->id)
        ->and($message->status)->toBe(ContactMessageStatus::New);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        NewContactMessage::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === ['bookings@baines.test']
            && $notification->contactMessage->is($message),
    );

    $this->get('/contact')->assertInertia(fn (Assert $page) => $page->where('sent', true));
});

it('sends no email when no staff emails are set', function () {
    Notification::fake();
    $settings = app(NotificationSettings::class);
    $settings->staff_emails = [];
    $settings->save();

    $this->post('/contact', ['name' => 'Amina', 'email' => 'amina@example.com', 'message' => 'Hello'])->assertRedirect('/contact');

    expect(ContactMessage::count())->toBe(1);
    Notification::assertNothingSent();
});

it('needs a name, a message and a way to reply', function () {
    $this->post('/contact', ['name' => '', 'message' => '', 'car' => 'not-a-car'])
        ->assertSessionHasErrors(['name', 'message', 'email', 'car'])
        ->assertSessionDoesntHaveErrors('phone');

    $this->post('/contact', ['name' => 'A', 'email' => 'not-an-email', 'phone' => 'call me', 'message' => 'Hi'])
        ->assertSessionHasErrors(['email', 'phone']);

    expect(ContactMessage::count())->toBe(0);
});

it('rejects a message that fails the security check', function () {
    config(['services.turnstile.secret_key' => 'real-secret']);
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => false])]);

    $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy now', 'cf-turnstile-response' => 'fake'])
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(ContactMessage::count())->toBe(0);
});

it('limits how many messages one address can send', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/contact', ['name' => "Renter {$i}", 'email' => "r{$i}@example.com", 'message' => 'Hi'])->assertRedirect();
    }

    $this->post('/contact', ['name' => 'Renter 6', 'email' => 'r6@example.com', 'message' => 'Hi'])->assertTooManyRequests();
    expect(ContactMessage::count())->toBe(5);
});
