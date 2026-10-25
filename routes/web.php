<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\BookingPaymentController;
use App\Http\Controllers\Public\BookingPortalController;
use App\Http\Controllers\Public\CarController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\CurrencyController;
use App\Http\Controllers\Public\FleetController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OnlinePaymentController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\QuotePreviewController;
use App\Http\Controllers\Public\RentalPolicyController;
use App\Http\Controllers\Public\ServicesController;
use App\Http\Controllers\Public\SitemapController;
use Illuminate\Support\Facades\Route;

// Public site (M7): every page shares the display currency (M6.13) and the site chrome.
Route::middleware(['currency', 'site'])->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('fleet', [FleetController::class, 'index'])->name('fleet');
    Route::get('cars/{car}', [CarController::class, 'show'])->name('cars.show');
    Route::get('car-brands/{brand}', [FleetController::class, 'brand'])->name('car-brands.show');
    Route::get('car-types/{carType}', [FleetController::class, 'carType'])->name('car-types.show');
    Route::get('services', ServicesController::class)->name('services');
    Route::get('about', [PageController::class, 'about'])->name('about');
    Route::get('rental-policy', RentalPolicyController::class)->name('rental-policy');
    Route::get('contact', [ContactController::class, 'show'])->name('contact');
    Route::get('privacy', [PageController::class, 'privacy'])->name('privacy');

    // Online booking (M8). The renter's link carries a secret token, never a booking id.
    Route::get('cars/{car}/book', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('bookings/{token}', [BookingController::class, 'show'])->middleware(['booking.token', 'throttle:booking-view'])->name('bookings.show');

    // Renter portal (M10B): find bookings by email or phone with an emailed code.
    Route::get('my-bookings', [BookingPortalController::class, 'index'])->name('portal.index');
});

Route::prefix('my-bookings')->name('portal.')->group(function () {
    Route::post('code', [BookingPortalController::class, 'sendCode'])->middleware('throttle:portal')->name('code');
    Route::post('verify', [BookingPortalController::class, 'verify'])->middleware('throttle:portal-verify')->name('verify');
    Route::post('restart', [BookingPortalController::class, 'restart'])->middleware('throttle:portal-session')->name('restart');

    Route::middleware('portal')->group(function () {
        Route::post('{reference}/open', [BookingPortalController::class, 'open'])->middleware('throttle:portal-session')->name('open');
        Route::get('{reference}/voucher', [BookingPortalController::class, 'voucher'])->name('voucher');
        Route::post('{reference}/requests', [BookingPortalController::class, 'storeRequest'])->middleware('throttle:booking-step')->name('requests.store');
    });
});

Route::post('cars/{car}/book', [BookingController::class, 'store'])->middleware('throttle:booking-start')->name('bookings.store');
Route::middleware(['booking.token', 'throttle:booking-step'])->prefix('bookings/{token}')->name('bookings.')->group(function () {
    Route::put('trip', [BookingController::class, 'updateTrip'])->name('trip');
    Route::put('driver', [BookingController::class, 'updateDriver'])->name('driver');
    Route::post('submit', [BookingController::class, 'submit'])->name('submit');
    Route::post('payment', [BookingPaymentController::class, 'store'])->name('payment');

    // Online payments (M11B.7).
    Route::middleware('throttle:online-payment')->prefix('payment')->name('payment.')->group(function () {
        Route::post('mpesa', [OnlinePaymentController::class, 'mpesa'])->name('mpesa');
        Route::post('card', [OnlinePaymentController::class, 'card'])->name('card');
        Route::post('card/confirm', [OnlinePaymentController::class, 'confirmCard'])->name('card.confirm');
    });
});

Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', fn () => response(
    "User-agent: *\nDisallow: /admin\nDisallow: /settings\nDisallow: /bookings\nDisallow: /my-bookings\n\nSitemap: ".route('sitemap')."\n",
)->header('Content-Type', 'text/plain; charset=UTF-8'))->name('robots');

Route::post('currency', [CurrencyController::class, 'update'])->middleware('throttle:30,1')->name('currency.update');
Route::post('quote/preview', QuotePreviewController::class)->middleware('throttle:quote-preview')->name('quote.preview');

// Kept so old links and Fortify defaults land in the admin.
Route::redirect('dashboard', '/admin');

// Local-only quick sign-in from the login page (404 elsewhere, see DevLogin).
Route::post('dev/login', DevLoginController::class)->middleware(['guest', 'throttle:30,1'])->name('dev.login');

require __DIR__.'/settings.php';
