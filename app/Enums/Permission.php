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

    // Catalog
    case BrandsView = 'brands.view';
    case BrandsCreate = 'brands.create';
    case BrandsUpdate = 'brands.update';
    case BrandsDelete = 'brands.delete';

    case CarTypesView = 'car-types.view';
    case CarTypesCreate = 'car-types.create';
    case CarTypesUpdate = 'car-types.update';
    case CarTypesDelete = 'car-types.delete';

    case CarsView = 'cars.view';
    case CarsCreate = 'cars.create';
    case CarsUpdate = 'cars.update';
    case CarsDelete = 'cars.delete';
    case CarsViewValue = 'cars.view-value';
    case CarsPublish = 'cars.publish';

    // Availability: maintenance blackouts now, the calendar in M11
    case AvailabilityManage = 'availability.manage';

    // Content
    case ServicesView = 'services.view';
    case ServicesCreate = 'services.create';
    case ServicesUpdate = 'services.update';
    case ServicesDelete = 'services.delete';

    // Preset pages: fixed in code, so there is no create or delete (D22)
    case PagesView = 'pages.view';
    case PagesUpdate = 'pages.update';

    case PoliciesView = 'policies.view';
    case PoliciesCreate = 'policies.create';
    case PoliciesUpdate = 'policies.update';
    case PoliciesDelete = 'policies.delete';
    case PoliciesPublish = 'policies.publish';

    // A fixed set of methods the booking flow branches on (D25)
    case PaymentMethodsView = 'payment-methods.view';
    case PaymentMethodsUpdate = 'payment-methods.update';

    case ContactMessagesView = 'contact-messages.view';
    case ContactMessagesUpdate = 'contact-messages.update';
    case ContactMessagesDelete = 'contact-messages.delete';

    // Bookings (M8). The bookings desk and its actions arrive in M11.
    case BookingsView = 'bookings.view';
    case BookingsViewDocuments = 'bookings.view-documents';

    // Pricing (M6). The PRD keeps pricing rules with the super admin by default.
    case CurrenciesView = 'currencies.view';
    case CurrenciesCreate = 'currencies.create';
    case CurrenciesUpdate = 'currencies.update';

    case FxRatesView = 'fx-rates.view';
    case FxRatesOverride = 'fx-rates.override';

    case ChargesView = 'charges.view';
    case ChargesCreate = 'charges.create';
    case ChargesUpdate = 'charges.update';
    case ChargesDelete = 'charges.delete';

    case DeliveryZonesView = 'delivery-zones.view';
    case DeliveryZonesCreate = 'delivery-zones.create';
    case DeliveryZonesUpdate = 'delivery-zones.update';
    case DeliveryZonesDelete = 'delivery-zones.delete';

    case DepositBandsView = 'deposit-bands.view';
    case DepositBandsCreate = 'deposit-bands.create';
    case DepositBandsUpdate = 'deposit-bands.update';
    case DepositBandsDelete = 'deposit-bands.delete';

    case CancellationTiersView = 'cancellation-tiers.view';
    case CancellationTiersCreate = 'cancellation-tiers.create';
    case CancellationTiersUpdate = 'cancellation-tiers.update';
    case CancellationTiersDelete = 'cancellation-tiers.delete';

    // The admin quote sandbox (M6.15), used for the Gate G3 session
    case QuotesSandbox = 'quotes.sandbox';

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
