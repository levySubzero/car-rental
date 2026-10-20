<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CarBlackoutController;
use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\CarTypeController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php with the "admin" prefix, the "admin." name
| prefix and the auth, verified, staff, 2fa and admin.idle middleware.
| Every route here must be authorized by a policy or a can: middleware;
| tests/Feature/Admin/RouteGuardTest.php fails the build otherwise.
|
*/

Route::inertia('/', 'admin/dashboard')->name('dashboard');

// IAM
Route::resource('users', UserController::class)->except('show');
Route::put('users/{user}/access', [UserController::class, 'access'])->name('users.access');
Route::put('users/{user}/active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
Route::post('users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('users.resend-invitation');

Route::resource('roles', RoleController::class)->except('show');

// Catalog. Order routes come before the resources, or {brand} would capture "order".
Route::put('brands/order', [BrandController::class, 'reorder'])->name('brands.reorder');
Route::put('brands/{brand}/marquee', [BrandController::class, 'marquee'])->name('brands.marquee');
Route::resource('brands', BrandController::class)->except('show');

Route::put('car-types/order', [CarTypeController::class, 'reorder'])->name('car-types.reorder');
Route::resource('car-types', CarTypeController::class)->except('show');

Route::post('cars/{car}/publish', [CarController::class, 'publish'])->name('cars.publish');
Route::delete('cars/{car}/publish', [CarController::class, 'unpublish'])->name('cars.unpublish');
Route::put('cars/{car}/availability', [CarController::class, 'availability'])->name('cars.availability');
Route::put('cars/{car}/featured', [CarController::class, 'featured'])->name('cars.featured');
Route::resource('cars', CarController::class)->except('show');
Route::scopeBindings()->group(function () {
    Route::post('cars/{car}/blackouts', [CarBlackoutController::class, 'store'])->name('cars.blackouts.store');
    Route::put('cars/{car}/blackouts/{blackout}', [CarBlackoutController::class, 'update'])->name('cars.blackouts.update');
    Route::delete('cars/{car}/blackouts/{blackout}', [CarBlackoutController::class, 'destroy'])->name('cars.blackouts.destroy');
});

// Settings
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::get('settings/{group}', [SettingsController::class, 'edit'])->middleware('can:update-settings,group')->name('settings.edit');
Route::put('settings/{group}', [SettingsController::class, 'update'])->middleware('can:update-settings,group')->name('settings.update');

// Audit
Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
