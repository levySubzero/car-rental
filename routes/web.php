<?php

use App\Http\Controllers\Auth\DevLoginController;
use Illuminate\Support\Facades\Route;

// Placeholder home until the public site lands (M7).
Route::inertia('/', 'welcome')->name('home');

// Kept so old links and Fortify defaults land in the admin.
Route::redirect('dashboard', '/admin');

// Local-only quick sign-in from the login page (404 elsewhere, see DevLogin).
Route::post('dev/login', DevLoginController::class)->middleware(['guest', 'throttle:30,1'])->name('dev.login');

require __DIR__.'/settings.php';
