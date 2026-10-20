<?php

use App\Enums\Permission;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatalogSeeder;

it('gives the starting roles their catalog permissions', function () {
    $this->seed(DatabaseSeeder::class);

    $editor = User::factory()->staff()->create()->assignRole('content-editor');
    $ops = User::factory()->staff()->create()->assignRole('operations');

    expect($editor->can(Permission::CarsUpdate))->toBeTrue()
        ->and($editor->can(Permission::CarsPublish))->toBeTrue()
        ->and($editor->can(Permission::BrandsDelete))->toBeTrue()
        ->and($editor->can(Permission::MediaUpload))->toBeTrue()
        ->and($editor->can(Permission::CarsViewValue))->toBeFalse()
        ->and($editor->can(Permission::AvailabilityManage))->toBeFalse()
        ->and($ops->can(Permission::CarsView))->toBeTrue()
        ->and($ops->can(Permission::AvailabilityManage))->toBeTrue()
        ->and($ops->can(Permission::CarsUpdate))->toBeFalse();
});

it('leaves permissions an admin changed alone when seeding again', function () {
    $this->seed(DatabaseSeeder::class);
    Role::findByName('content-editor')->revokePermissionTo(Permission::CarsDelete);

    $this->seed(DatabaseSeeder::class);

    expect(Role::findByName('content-editor')->hasPermissionTo(Permission::CarsDelete))->toBeFalse();
});

it('seeds the demo catalogue once', function () {
    $this->seed(DemoCatalogSeeder::class);
    $this->seed(DemoCatalogSeeder::class);

    expect(Brand::count())->toBe(8)
        ->and(CarType::count())->toBe(6)
        ->and(Car::count())->toBe(12)
        ->and(Car::firstWhere('name', 'Toyota Land Cruiser V8')->metadata['drive'])->toBe('4WD')
        ->and(Car::query()->published()->count())->toBe(0);
});
