<?php

use App\Http\Controllers\Media\MediaController;
use Illuminate\Support\Facades\Route;

/*
| The only media routes in the app (TECHNICAL_PLAN §6.4). Loaded in bootstrap/app.php
| with the web group and media.uploader, but not auth: renters upload with a booking
| token. MediaPolicy decides every request.
*/

Route::post('media', [MediaController::class, 'store'])
    ->middleware('throttle:media-upload')
    ->name('media.store');

Route::post('media/reorder', [MediaController::class, 'reorder'])
    ->middleware('throttle:media-upload')
    ->name('media.reorder');

Route::get('media/s/{media}/{conversion?}', [MediaController::class, 'signed'])
    ->middleware('signed')
    ->name('media.signed');

Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');

Route::get('media/{media}/{conversion?}', [MediaController::class, 'show'])
    ->whereIn('conversion', array_keys((array) config('media.conversions')))
    ->name('media.show');

Route::patch('media/{media}', [MediaController::class, 'update'])
    ->middleware('throttle:media-upload')
    ->name('media.update');

Route::delete('media/{media}', [MediaController::class, 'destroy'])
    ->middleware('throttle:media-upload')
    ->name('media.destroy');
