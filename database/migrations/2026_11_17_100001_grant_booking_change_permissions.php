<?php

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Extend and edit booking go to the live booking desk, as every other bookings permission
     * does on a new install. The Email log (mail-log.view) stays with the super admin.
     */
    public function up(): void
    {
        $role = Role::query()->where('name', 'operations')->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        foreach ([Permission::BookingsEdit, Permission::BookingsExtend] as $permission) {
            $role->givePermissionTo(PermissionModel::findOrCreate($permission->value, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
