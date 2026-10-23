<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/*
|--------------------------------------------------------------------------
| Layering rules (TECHNICAL_PLAN §2 and §16)
|--------------------------------------------------------------------------
*/

arch('controllers do not touch the database or storage directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Storage',
    ]);

arch('only the media module touches storage')
    ->expect('Illuminate\Support\Facades\Storage')
    ->toOnlyBeUsedIn([
        'App\Services\Media',
    ]);

arch('services do not read the request or render responses')
    ->expect('App\Services')
    ->not->toUse([
        'request',
        'Illuminate\Http\Request',
        'Illuminate\Http\Response',
        'Illuminate\Http\RedirectResponse',
        'Inertia\Inertia',
        'response',
        'view',
    ]);

arch('models do not depend on services')
    ->expect('App\Models')
    ->not->toUse('App\Services');

arch('form requests authorize through policies')
    ->expect('App\Http\Requests')
    ->classes()
    ->toExtend('Illuminate\Foundation\Http\FormRequest')
    ->toHaveMethod('authorize')
    ->ignoring(['App\Http\Requests\Admin\User\Concerns', 'App\Http\Requests\Public\Booking\Concerns']);

arch('policies check permissions, never role names')
    ->expect('App\Policies')
    ->not->toUse(['hasRole', 'hasAnyRole', 'hasAllRoles', 'assignRole']);

arch('policies check permissions through the Permission enum')
    ->expect('App\Policies')
    ->toUse('App\Enums\Permission');

arch('no debugging helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeEnums();

test('public resources never expose the car value', function () {
    $directory = app_path('Http/Resources/Public');

    $offenders = collect(is_dir($directory) ? File::allFiles($directory) : [])
        ->filter(fn (SplFileInfo $file) => str_contains($file->getContents(), 'car_value'))
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('public resources never expose metadata or raw Markdown', function () {
    $directory = app_path('Http/Resources/Public');

    // Markdown reaches the public site only as *_html (D23); metadata stays internal (D20).
    $offenders = collect(is_dir($directory) ? File::allFiles($directory) : [])
        ->filter(fn (SplFileInfo $file) => preg_match("/'(metadata|body|instructions)'\\s*=>/", $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
