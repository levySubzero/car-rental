<?php

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The seeder only fills a role when it first creates it, so a live booking desk needs its
     * new permissions here. On a new install the role does not exist yet.
     */
    public function up(): void
    {
        $role = Role::query()->where('name', 'operations')->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        foreach ([Permission::BookingsAccept, Permission::BookingsCreate] as $permission) {
            $role->givePermissionTo(PermissionModel::findOrCreate($permission->value, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
