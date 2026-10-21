<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Public\CurrencyController;
use App\Http\Controllers\Public\QuotePreviewController;
use Illuminate\Support\Facades\Route;

// Public site: every page shares the display currency (M6.13).
Route::middleware('currency')->group(function () {
    // Placeholder home until the public site lands (M7).
    Route::inertia('/', 'welcome')->name('home');
});

Route::post('currency', [CurrencyController::class, 'update'])->middleware('throttle:30,1')->name('currency.update');
Route::post('quote/preview', QuotePreviewController::class)->middleware('throttle:quote-preview')->name('quote.preview');

// Kept so old links and Fortify defaults land in the admin.
Route::redirect('dashboard', '/admin');

// Local-only quick sign-in from the login page (404 elsewhere, see DevLogin).
Route::post('dev/login', DevLoginController::class)->middleware(['guest', 'throttle:30,1'])->name('dev.login');

require __DIR__.'/settings.php';
