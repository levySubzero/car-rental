<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A staff role created and edited in the admin. The super admin role is the
 * only system role: it is seeded, locked, and passes every check through Gate::before.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $description
 * @property bool $is_system
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'description', 'is_system'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === config('baines.super_admin_role');
    }
}
