<?php

namespace App\Services\Iam;

use App\Exceptions\Domain\RoleManagementException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Staff roles are data: admins create them and choose their permissions.
 */
class RoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        /** @var array<int, string> */
        return Role::query()->orderByDesc('is_system')->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  array{name: string, description?: string|null, permissions?: list<string>}  $data
     */
    public function create(array $data, User $actor): Role
    {
        return DB::transaction(function () use ($data, $actor) {
            /** @var Role $role */
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => config('auth.defaults.guard', 'web'),
                'description' => $data['description'] ?? null,
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            activity('roles')->performedOn($role)->causedBy($actor)
                ->withProperties(['permissions' => $data['permissions'] ?? []])
                ->event('created')
                ->log('created');

            return $role;
        });
    }

    /**
     * @param  array{name: string, description?: string|null, permissions?: list<string>}  $data
     */
    public function update(Role $role, array $data, User $actor): Role
    {
        $this->ensureEditable($role);

        return DB::transaction(function () use ($role, $data, $actor) {
            $before = $this->snapshot($role);

            $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $role->syncPermissions($data['permissions'] ?? []);
            $role->unsetRelation('permissions');

            $after = $this->snapshot($role);

            if ($before !== $after) {
                activity('roles')->performedOn($role)->causedBy($actor)
                    ->withChanges(['attributes' => $after, 'old' => $before])
                    ->event('updated')
                    ->log('updated');
            }

            return $role;
        });
    }

    public function delete(Role $role, User $actor): void
    {
        $this->ensureEditable($role);

        $users = $role->users()->count();

        if ($users > 0) {
            throw RoleManagementException::stillAssigned($users);
        }

        DB::transaction(function () use ($role, $actor) {
            activity('roles')->causedBy($actor)
                ->withProperties(['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()])
                ->event('deleted')
                ->log("role {$role->name} deleted");

            $role->delete();
        });
    }

    private function ensureEditable(Role $role): void
    {
        if ($role->is_system || $role->isSuperAdmin()) {
            throw RoleManagementException::systemRoleLocked();
        }
    }

    /**
     * @return array{name: string, description: string|null, permissions: array<int, string>}
     */
    private function snapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
