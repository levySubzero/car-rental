<?php

use Illuminate\Support\Facades\Route;

// Placeholder home until the public site lands (M7).
Route::inertia('/', 'welcome')->name('home');

// Kept so old links and Fortify defaults land in the admin.
Route::redirect('dashboard', '/admin');

require __DIR__.'/settings.php';
