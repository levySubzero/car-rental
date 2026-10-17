<?php

use App\Http\Controllers\Admin\ActivityLogController;
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

// Settings
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::get('settings/{group}', [SettingsController::class, 'edit'])->middleware('can:update-settings,group')->name('settings.edit');
Route::put('settings/{group}', [SettingsController::class, 'update'])->middleware('can:update-settings,group')->name('settings.update');

// Audit
Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
