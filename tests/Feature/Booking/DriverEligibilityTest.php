<?php

use App\Enums\BookingDocument;
use App\Enums\IdType;
use App\Enums\KraPinRule;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Services\Booking\DriverEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00'));
    $this->booking = Booking::factory()->create(['pickup_at' => '2026-11-10 07:00', 'return_at' => '2026-11-13 07:00']);
    $this->service = app(DriverEligibilityService::class);
});

it('reads the document matrix per id type and residency', function () {
    expect($this->service->requirementsFor(IdType::NationalId, true))->toBe([
        'documents' => [BookingDocument::IdFront, BookingDocument::IdBack, BookingDocument::KraCertificate, BookingDocument::Licence],
        'kra_pin' => KraPinRule::Required,
    ])->and($this->service->requirementsFor(IdType::Passport, false)['kra_pin'])->toBe(KraPinRule::Optional);
});

it('passes a resident with every document', function () {
    $driver = BookingDriver::factory()->for($this->booking)->create();
    BookingFixtures::attachDocuments($this->booking);

    expect($this->service->check($this->booking, $driver))->toBe([]);
});

it('names each missing document and the KRA PIN', function () {
    $driver = BookingDriver::factory()->for($this->booking)->create(['kra_pin' => null]);
    BookingFixtures::attachDocuments($this->booking, [BookingDocument::IdFront]);

    expect(array_keys($this->service->check($this->booking, $driver)))
        ->toBe(['kra_pin', 'documents.id_back', 'documents.kra_certificate', 'documents.licence']);
});

it('asks passport holders for their passport and licence only', function () {
    $driver = BookingDriver::factory()->passport()->for($this->booking)->create();
    BookingFixtures::attachDocuments($this->booking, [BookingDocument::Passport, BookingDocument::Licence]);

    expect($this->service->check($this->booking, $driver))->toBe([]);
});

it('refuses a licence that expires by the return date, or held too briefly', function () {
    // The return is 13 Nov at 10:00 in Nairobi: a licence ending that day has run out.
    $driver = BookingDriver::factory()->for($this->booking)->create(['licence_expiry' => '2026-11-13', 'licence_years_declared' => 2]);
    BookingFixtures::attachDocuments($this->booking);

    expect(array_keys($this->service->check($this->booking, $driver)))->toBe(['licence_expiry', 'licence_years_declared']);

    $driver->update(['licence_expiry' => '2026-11-14', 'licence_years_declared' => 3]);
    expect($this->service->check($this->booking, $driver->fresh()))->toBe([]);
});

it('warns about short notice for a flight, without refusing', function () {
    $this->booking->update(['flight_number' => 'KQ101']);
    expect($this->service->warnings($this->booking))->toHaveCount(0);

    $this->booking->update(['pickup_at' => '2026-11-02 07:00']);
    expect($this->service->warnings($this->booking))->toHaveCount(1);
});

it('encrypts identity numbers and keeps a keyed hash of the id number', function () {
    $driver = BookingDriver::factory()->for($this->booking)->create(['id_number' => '12 345 678']);
    $row = DB::table('booking_drivers')->find($driver->id);

    expect($row->id_number)->not->toContain('12 345 678')
        ->and($row->licence_number)->not->toBe($driver->licence_number)
        ->and($driver->fresh()->id_number)->toBe('12 345 678')
        ->and($row->id_number_hash)->toBe(BookingDriver::hashIdNumber('12345678'))
        ->and($row->id_number_hash)->not->toBe(hash('sha256', '12345678'))
        ->and($driver->toArray())->not->toHaveKeys(['id_number', 'kra_pin', 'licence_number', 'id_number_hash']);
});
