<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Every permission in the app (TECHNICAL_PLAN §5). Values are "{module}.{ability}".
 *
 * Each milestone adds its module's cases here, then `php artisan permissions:sync`
 * writes them to the database and `php artisan permissions:types` regenerates
 * resources/js/types/permissions.ts. Roles are data: admins pick these permissions
 * for each role in the admin.
 *
 * Check with $user->can(Permission::UsersView). Never compare role names.
 */
enum Permission: string
{
    // Users
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case UsersAssignRoles = 'users.assign-roles';
    case UsersGrantPermissions = 'users.grant-permissions';

    // Roles
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    // Settings: one update permission per settings group (SettingsGroup)
    case SettingsGeneralUpdate = 'settings.general.update';
    case SettingsPricingUpdate = 'settings.pricing.update';
    case SettingsBookingUpdate = 'settings.booking.update';
    case SettingsFxUpdate = 'settings.fx.update';
    case SettingsNotificationUpdate = 'settings.notification.update';
    case SettingsHomeUpdate = 'settings.home.update';
    case SettingsRetentionUpdate = 'settings.retention.update';
    case SettingsSecurityUpdate = 'settings.security.update';
    case SettingsMediaUpdate = 'settings.media.update';

    // Media: uploading also needs the collection's own permission (config/media.php)
    case MediaUpload = 'media.upload';
    case MediaViewPrivate = 'media.view-private';

    // Activity log
    case ActivityView = 'activity.view';

    // System
    case SystemHorizon = 'system.horizon';

    /**
     * The module part: "settings" for settings.general.update.
     */
    public function module(): string
    {
        return Str::before($this->value, '.');
    }

    /**
     * The ability part: "general.update" for settings.general.update.
     */
    public function ability(): string
    {
        return Str::after($this->value, '.');
    }

    /**
     * Human label for admin checklists: "Assign roles", "General update".
     */
    public function label(): string
    {
        return Str::ucfirst(str_replace(['-', '.'], ' ', $this->ability()));
    }

    /**
     * Every permission of the given modules, in declaration order.
     *
     * @return list<self>
     */
    public static function forModule(string ...$modules): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission) => in_array($permission->module(), $modules, true),
        ));
    }

    /**
     * Permissions from $permissions without the excluded ones.
     *
     * @param  list<self>  $permissions
     * @return list<self>
     */
    public static function except(array $permissions, self ...$excluded): array
    {
        return array_values(array_filter(
            $permissions,
            fn (self $permission) => ! in_array($permission, $excluded, true),
        ));
    }

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return array_values(array_unique(array_map(fn (self $permission) => $permission->module(), self::cases())));
    }

    /**
     * Permission names grouped by module, for checklists: ['users' => ['users.view', ...]].
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $permission) {
            $grouped[$permission->module()][] = $permission->value;
        }

        return $grouped;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
