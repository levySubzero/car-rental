<?php

namespace App\Enums;

use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateHomeSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateMediaSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateNotificationSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateRetentionSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateSecuritySettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateSettingsRequest;
use App\Settings\BookingSettings;
use App\Settings\FxSettings;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;
use App\Settings\MediaSettings;
use App\Settings\NotificationSettings;
use App\Settings\PricingSettings;
use App\Settings\RetentionSettings;
use App\Settings\SecuritySettings;
use Spatie\LaravelSettings\Settings;

/**
 * The settings groups shown as tabs on the admin settings page.
 */
enum SettingsGroup: string
{
    case General = 'general';
    case Pricing = 'pricing';
    case Booking = 'booking';
    case Fx = 'fx';
    case Notification = 'notification';
    case Home = 'home';
    case Retention = 'retention';
    case Security = 'security';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Pricing => 'Pricing',
            self::Booking => 'Booking',
            self::Fx => 'Exchange rates',
            self::Notification => 'Notifications',
            self::Home => 'Home page',
            self::Retention => 'Data retention',
            self::Security => 'Security',
            self::Media => 'Uploads',
        };
    }

    /**
     * @return class-string<Settings>
     */
    public function settingsClass(): string
    {
        return match ($this) {
            self::General => GeneralSettings::class,
            self::Pricing => PricingSettings::class,
            self::Booking => BookingSettings::class,
            self::Fx => FxSettings::class,
            self::Notification => NotificationSettings::class,
            self::Home => HomeSettings::class,
            self::Retention => RetentionSettings::class,
            self::Security => SecuritySettings::class,
            self::Media => MediaSettings::class,
        };
    }

    public function permission(): Permission
    {
        return match ($this) {
            self::General => Permission::SettingsGeneralUpdate,
            self::Pricing => Permission::SettingsPricingUpdate,
            self::Booking => Permission::SettingsBookingUpdate,
            self::Fx => Permission::SettingsFxUpdate,
            self::Notification => Permission::SettingsNotificationUpdate,
            self::Home => Permission::SettingsHomeUpdate,
            self::Retention => Permission::SettingsRetentionUpdate,
            self::Security => Permission::SettingsSecurityUpdate,
            self::Media => Permission::SettingsMediaUpdate,
        };
    }

    /**
     * The FormRequest that validates this tab, or null while the tab is still to be built:
     * Pricing (M6.14), Booking (M8.8) and Fx (M6.4).
     *
     * @return class-string<UpdateSettingsRequest>|null
     */
    public function requestClass(): ?string
    {
        return match ($this) {
            self::General => UpdateGeneralSettingsRequest::class,
            self::Notification => UpdateNotificationSettingsRequest::class,
            self::Home => UpdateHomeSettingsRequest::class,
            self::Retention => UpdateRetentionSettingsRequest::class,
            self::Security => UpdateSecuritySettingsRequest::class,
            self::Media => UpdateMediaSettingsRequest::class,
            default => null,
        };
    }

    public function isEditable(): bool
    {
        return $this->requestClass() !== null;
    }
}
