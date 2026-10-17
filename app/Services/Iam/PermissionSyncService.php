<?php

namespace App\Services\Iam;

use App\Enums\Permission as PermissionEnum;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes the Permission enum to the database and keeps the locked super admin
 * role in place. Other roles are data managed in the admin and are left alone.
 * Safe to run repeatedly.
 */
class PermissionSyncService
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * @return array{created: list<string>, orphaned: list<string>, pruned: bool}
     */
    public function sync(bool $prune = false): array
    {
        $guard = config('auth.defaults.guard', 'web');
        $permissions = PermissionEnum::values();

        $result = DB::transaction(function () use ($guard, $permissions, $prune) {
            $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();
            $created = array_values(array_diff($permissions, $existing));

            foreach ($created as $name) {
                Permission::create(['name' => $name, 'guard_name' => $guard]);
            }

            $orphaned = array_values(array_diff($existing, $permissions));

            if ($prune && $orphaned !== []) {
                Permission::query()->where('guard_name', $guard)->whereIn('name', $orphaned)->delete();
            }

            $this->registrar->forgetCachedPermissions();

            $superAdmin = Role::query()->firstOrCreate(
                ['name' => config('baines.super_admin_role'), 'guard_name' => $guard],
                ['description' => 'Full access to everything, including staff and settings.'],
            );
            $superAdmin->forceFill(['is_system' => true])->save();
            $superAdmin->syncPermissions($permissions);

            return ['created' => $created, 'orphaned' => $orphaned, 'pruned' => $prune];
        });

        $this->registrar->forgetCachedPermissions();

        return $result;
    }
}
