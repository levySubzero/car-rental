<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BookingCancellationController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BookingDeliveryController;
use App\Http\Controllers\Admin\BookingHandoverController;
use App\Http\Controllers\Admin\BookingRequestController;
use App\Http\Controllers\Admin\BookingVoucherController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CancellationTierController;
use App\Http\Controllers\Admin\CarBlackoutController;
use App\Http\Controllers\Admin\CarController;
use App\Http\Controllers\Admin\CarTypeController;
use App\Http\Controllers\Admin\ChargeController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryZoneController;
use App\Http\Controllers\Admin\DepositBandController;
use App\Http\Controllers\Admin\FxRateController;
use App\Http\Controllers\Admin\GatewayReceiptController;
use App\Http\Controllers\Admin\MarkdownPreviewController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentSubmissionController;
use App\Http\Controllers\Admin\PolicyController;
use App\Http\Controllers\Admin\QuoteSandboxController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SetupReminderController;
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

Route::get('/', DashboardController::class)->name('dashboard');

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
Route::put('car-types/{car_type}/visibility', [CarTypeController::class, 'visibility'])->name('car-types.visibility');
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

// Pricing (M6). Order routes come before the resources, as above.
Route::put('currencies/order', [CurrencyController::class, 'reorder'])->name('currencies.reorder');
Route::put('currencies/{currency}/active', [CurrencyController::class, 'active'])->name('currencies.active');
Route::resource('currencies', CurrencyController::class)->except(['show', 'destroy']);

Route::get('fx-rates', [FxRateController::class, 'index'])->name('fx-rates.index');
Route::post('fx-rates', [FxRateController::class, 'store'])->name('fx-rates.store');
Route::post('fx-rates/refresh', [FxRateController::class, 'refresh'])->name('fx-rates.refresh');
Route::put('fx-rates/{fx_rate}/expire', [FxRateController::class, 'expire'])->name('fx-rates.expire');

Route::put('charges/order', [ChargeController::class, 'reorder'])->name('charges.reorder');
Route::resource('charges', ChargeController::class)->except('show');

Route::put('delivery-zones/order', [DeliveryZoneController::class, 'reorder'])->name('delivery-zones.reorder');
Route::resource('delivery-zones', DeliveryZoneController::class)->except('show');

Route::resource('deposit-bands', DepositBandController::class)->except('show');
Route::resource('cancellation-tiers', CancellationTierController::class)->except('show');

Route::get('quote-sandbox', QuoteSandboxController::class)->name('quote-sandbox');

// Content: one sidebar entry with a tab per module, like settings. URLs live under
// /admin/content so the sidebar highlights it; route names keep their module prefix.
// Order routes come before the resources, as above.
Route::get('content', ContentController::class)->name('content.index');
Route::prefix('content')->group(function () {
    Route::put('services/order', [ServiceController::class, 'reorder'])->name('services.reorder');
    Route::put('services/{service}/active', [ServiceController::class, 'active'])->name('services.active');
    Route::resource('services', ServiceController::class)->except('show');

    // Preset pages are fixed in code (D22): no create or delete.
    Route::get('pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');

    Route::put('policies/order', [PolicyController::class, 'reorder'])->name('policies.reorder');
    Route::put('policies/{policy}/home', [PolicyController::class, 'home'])->name('policies.home');
    Route::put('policies/{policy}/draft', [PolicyController::class, 'saveDraft'])->name('policies.draft.save');
    Route::delete('policies/{policy}/draft', [PolicyController::class, 'discardDraft'])->name('policies.draft.discard');
    Route::post('policies/{policy}/publish', [PolicyController::class, 'publish'])->name('policies.publish');
    Route::resource('policies', PolicyController::class)->except('show');

    // A fixed set of methods (D25): no create or delete.
    Route::put('payment-methods/order', [PaymentMethodController::class, 'reorder'])->name('payment-methods.reorder');
    Route::put('payment-methods/{payment_method}/active', [PaymentMethodController::class, 'active'])->name('payment-methods.active');
    Route::resource('payment-methods', PaymentMethodController::class)->only(['index', 'edit', 'update']);
});

// Operations
Route::resource('contact-messages', ContactMessageController::class)->only(['index', 'show', 'update', 'destroy']);

// The before-launch popup, closed for the rest of the session (M11B.10).
Route::post('setup-reminder/dismiss', SetupReminderController::class)->name('setup-reminder.dismiss');

// Payments (M9): the queue of renter payments to check.
Route::get('payments', [PaymentSubmissionController::class, 'index'])->name('payments.index');
Route::post('payments/{payment}/verify', [PaymentSubmissionController::class, 'verify'])->name('payments.verify');
Route::post('payments/{payment}/reject', [PaymentSubmissionController::class, 'reject'])->name('payments.reject');

// Online receipts (M11B.9): paybill payments naming no booking, and payments held for review.
Route::get('receipts', [GatewayReceiptController::class, 'index'])->name('receipts.index');
Route::post('receipts/{receipt}/attach', [GatewayReceiptController::class, 'attach'])->name('receipts.attach');
Route::post('receipts/{receipt}/resolve', [GatewayReceiptController::class, 'resolve'])->name('receipts.resolve');

// Bookings desk (M11): bookings by reference, and the actions staff take on them.
Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::prefix('bookings/{booking:reference}')->name('bookings.')->group(function () {
    Route::get('/', [BookingController::class, 'show'])->name('show');
    Route::post('handover', [BookingHandoverController::class, 'handover'])->name('handover');
    Route::post('return', [BookingHandoverController::class, 'return'])->name('return');
    Route::post('cancel', [BookingCancellationController::class, 'cancel'])->name('cancel');
    Route::post('no-show', [BookingCancellationController::class, 'noShow'])->name('no-show');
    Route::post('refunded', [BookingCancellationController::class, 'refunded'])->name('refunded');
    Route::put('delivery', [BookingDeliveryController::class, 'update'])->name('delivery.update');
    Route::post('delivery/checked', [BookingDeliveryController::class, 'checked'])->name('delivery.checked');
    Route::post('voucher', [BookingVoucherController::class, 'resend'])->name('voucher.resend');
    Route::post('link', [BookingVoucherController::class, 'rotate'])->name('link.rotate');
    Route::put('legal-hold', [BookingController::class, 'legalHold'])->name('legal-hold');
});

// Availability calendar (M11.9)
Route::get('availability', [AvailabilityController::class, 'index'])->name('availability.index');

// Booking requests (M10B): cancellations and changes renters ask for in the portal.
Route::get('booking-requests', [BookingRequestController::class, 'index'])->name('booking-requests.index');
Route::post('booking-requests/{booking_request}/handle', [BookingRequestController::class, 'handle'])->name('booking-requests.handle');

Route::post('markdown/preview', MarkdownPreviewController::class)->name('markdown.preview');

// In-app alerts (M10B): the signed-in staff member's own, so no permission is needed.
Route::post('alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.read-all');
Route::post('alerts/{alert}/open', [AlertController::class, 'open'])->whereUuid('alert')->name('alerts.open');

// Settings
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::get('settings/{group}', [SettingsController::class, 'edit'])->middleware('can:update-settings,group')->name('settings.edit');
Route::put('settings/{group}', [SettingsController::class, 'update'])->middleware('can:update-settings,group')->name('settings.update');

// Audit
Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
