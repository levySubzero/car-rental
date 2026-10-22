<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Public\CarController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\CurrencyController;
use App\Http\Controllers\Public\FleetController;
use App\Http\Controllers\Public\HomeController;
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
});

Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', fn () => response(
    "User-agent: *\nDisallow: /admin\nDisallow: /settings\n\nSitemap: ".route('sitemap')."\n",
)->header('Content-Type', 'text/plain; charset=UTF-8'))->name('robots');

Route::post('currency', [CurrencyController::class, 'update'])->middleware('throttle:30,1')->name('currency.update');
Route::post('quote/preview', QuotePreviewController::class)->middleware('throttle:quote-preview')->name('quote.preview');

// Kept so old links and Fortify defaults land in the admin.
Route::redirect('dashboard', '/admin');

// Local-only quick sign-in from the login page (404 elsewhere, see DevLogin).
Route::post('dev/login', DevLoginController::class)->middleware(['guest', 'throttle:30,1'])->name('dev.login');

require __DIR__.'/settings.php';
