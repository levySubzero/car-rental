<?php

use App\Enums\MediaVisibility;
use App\Exceptions\Domain\MediaException;
use App\Models\User;
use App\Services\Media\MediaCollectionRegistry;

function registry(array $overrides = [], array $unsupported = [], ?array $definitions = null): MediaCollectionRegistry
{
    return new MediaCollectionRegistry(
        definitions: $definitions ?? [
            'gadget.photos' => [
                'label' => 'Gadget photos',
                'owner' => 'user',
                'visibility' => 'public',
                'max_files' => 6,
                'max_kb' => 4096,
                'mimes' => ['jpg', 'png', 'webp', 'heic'],
                'conversions' => ['thumb', 'card'],
                'upload' => ['permission' => 'users.update'],
            ],
            'gadget.docs' => [
                'owner' => 'user',
                'visibility' => 'private',
                'max_files' => 1,
                'max_kb' => 99_999,
                'mimes' => ['pdf', 'jpg'],
                'upload' => ['token' => true],
            ],
            'site.banner' => [
                'visibility' => 'public',
                'mimes' => ['jpg'],
                'upload' => ['permission' => 'settings.home.update'],
            ],
        ],
        overrides: $overrides,
        unsupportedExtensions: $unsupported,
        kbCeiling: 8192,
    );
}

it('reads collections from config', function () {
    $photos = registry()->get('gadget.photos');

    expect($photos->label)->toBe('Gadget photos')
        ->and($photos->owner)->toBe('user')
        ->and($photos->visibility)->toBe(MediaVisibility::Public)
        ->and($photos->maxFiles)->toBe(6)
        ->and($photos->maxKb)->toBe(4096)
        ->and($photos->conversions)->toBe(['thumb', 'card'])
        ->and($photos->uploadPermission)->toBe('users.update')
        ->and($photos->mimeTypes())->toBe(['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif']);
});

it('caps max_kb at the app-wide ceiling', function () {
    expect(registry()->get('gadget.docs')->maxKb)->toBe(8192);
});

it('lets overrides tighten limits but never raise them', function () {
    $registry = registry(['gadget.photos' => ['max_files' => 3, 'max_kb' => 1024, 'mimes' => ['png', 'svg']]]);

    $photos = $registry->get('gadget.photos');

    expect($photos->maxFiles)->toBe(3)
        ->and($photos->maxKb)->toBe(1024)
        ->and($photos->mimes)->toBe(['png'])
        ->and($registry->ceiling('gadget.photos')->maxFiles)->toBe(6);

    $raised = registry(['gadget.photos' => ['max_files' => 50, 'max_kb' => 99_999]])->get('gadget.photos');

    expect($raised->maxFiles)->toBe(6)->and($raised->maxKb)->toBe(4096);
});

it('ignores a mime override that would leave no types', function () {
    expect(registry(['gadget.photos' => ['mimes' => ['pdf']]])->get('gadget.photos')->mimes)
        ->toBe(['jpg', 'png', 'webp', 'heic']);
});

it('drops extensions the image driver cannot decode', function () {
    $registry = registry(unsupported: ['heic']);

    expect($registry->get('gadget.photos')->mimes)->toBe(['jpg', 'png', 'webp'])
        ->and($registry->ceiling('gadget.photos')->mimes)->not->toContain('heic');
});

it('throws for an unknown collection', function () {
    registry()->get('nope');
})->throws(MediaException::class, 'Unknown media collection [nope].');

it('lists collections by owner alias', function () {
    expect(array_keys(registry()->forOwner('user')))->toBe(['gadget.photos', 'gadget.docs'])
        ->and(registry()->get('site.banner')->isStandalone())->toBeTrue();
});

it('checks that an owner matches the collection', function () {
    $user = new User;

    expect(registry()->assertOwner('gadget.photos', $user)->key)->toBe('gadget.photos')
        ->and(registry()->assertOwner('site.banner', null)->key)->toBe('site.banner');

    expect(fn () => registry()->assertOwner('site.banner', $user))->toThrow(MediaException::class)
        ->and(fn () => registry()->assertOwner('gadget.photos', null))->toThrow(MediaException::class);
});

it('rejects invalid definitions', function (array $definition, string $message) {
    expect(fn () => registry(definitions: ['bad.one' => $definition]))->toThrow(LogicException::class, $message);
})->with([
    'unknown extension' => [['mimes' => ['exe'], 'upload' => ['system' => true]], 'unknown extensions'],
    'no extensions' => [['mimes' => [], 'upload' => ['system' => true]], 'no or unknown'],
    'unknown conversion' => [['mimes' => ['jpg'], 'conversions' => ['huge'], 'upload' => ['system' => true]], 'unknown conversions'],
    'no upload rule' => [['mimes' => ['jpg']], 'no upload rule'],
    'token without owner' => [['mimes' => ['jpg'], 'upload' => ['token' => true]], 'no owner'],
]);
