import { Head, Link } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import type {
    BookingSettings,
    BookingSettingsContext,
} from '@/components/admin/settings/booking-settings-form';
import { BookingSettingsForm } from '@/components/admin/settings/booking-settings-form';
import type {
    FxSettings,
    FxSettingsContext,
} from '@/components/admin/settings/fx-settings-form';
import { FxSettingsForm } from '@/components/admin/settings/fx-settings-form';
import type { GeneralSettings } from '@/components/admin/settings/general-settings-form';
import { GeneralSettingsForm } from '@/components/admin/settings/general-settings-form';
import type {
    HomeSettings,
    HomeSettingsContext,
} from '@/components/admin/settings/home-settings-form';
import { HomeSettingsForm } from '@/components/admin/settings/home-settings-form';
import type { MediaSettingsContext } from '@/components/admin/settings/media-settings-form';
import { MediaSettingsForm } from '@/components/admin/settings/media-settings-form';
import type { NotificationSettings } from '@/components/admin/settings/notification-settings-form';
import { NotificationSettingsForm } from '@/components/admin/settings/notification-settings-form';
import type {
    PricingSettings,
    PricingSettingsContext,
} from '@/components/admin/settings/pricing-settings-form';
import { PricingSettingsForm } from '@/components/admin/settings/pricing-settings-form';
import type { RetentionSettings } from '@/components/admin/settings/retention-settings-form';
import { RetentionSettingsForm } from '@/components/admin/settings/retention-settings-form';
import type { SecuritySettings } from '@/components/admin/settings/security-settings-form';
import { SecuritySettingsForm } from '@/components/admin/settings/security-settings-form';
import { cn } from '@/lib/utils';
import type { SettingsTab } from '@/types';

export default function EditSettings({
    group,
    tabs,
    values,
    context,
}: {
    group: string;
    tabs: SettingsTab[];
    values: Record<string, unknown>;
    context: Record<string, unknown>;
}) {
    const current = tabs.find((tab) => tab.key === group);

    return (
        <>
            <Head title={`${current?.label ?? 'Settings'} settings`} />
            <AdminPage>
                <PageHeader
                    title="Settings"
                    description="Changes apply to new bookings only. Existing bookings keep the values they were made with."
                />
                <nav
                    className="flex gap-1 overflow-x-auto border-b"
                    aria-label="Settings sections"
                >
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={SettingsController.edit(tab.key)}
                            preserveScroll
                            aria-current={
                                tab.key === group ? 'page' : undefined
                            }
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                                tab.key === group
                                    ? 'border-primary font-medium text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>
                <div className="max-w-4xl">
                    {/* key resets form state when switching tabs */}
                    {group === 'general' && (
                        <GeneralSettingsForm
                            key={group}
                            values={values as GeneralSettings}
                        />
                    )}
                    {group === 'pricing' && (
                        <PricingSettingsForm
                            key={group}
                            values={values as PricingSettings}
                            context={context as PricingSettingsContext}
                        />
                    )}
                    {group === 'booking' && (
                        <BookingSettingsForm
                            key={group}
                            values={values as BookingSettings}
                            context={context as BookingSettingsContext}
                        />
                    )}
                    {group === 'fx' && (
                        <FxSettingsForm
                            key={group}
                            values={values as FxSettings}
                            context={context as FxSettingsContext}
                        />
                    )}
                    {group === 'home' && (
                        <HomeSettingsForm
                            key={group}
                            values={values as HomeSettings}
                            context={context as HomeSettingsContext}
                        />
                    )}
                    {group === 'notification' && (
                        <NotificationSettingsForm
                            key={group}
                            values={values as NotificationSettings}
                        />
                    )}
                    {group === 'security' && (
                        <SecuritySettingsForm
                            key={group}
                            values={values as SecuritySettings}
                        />
                    )}
                    {group === 'media' && (
                        <MediaSettingsForm
                            key={group}
                            context={context as MediaSettingsContext}
                        />
                    )}
                    {group === 'retention' && (
                        <RetentionSettingsForm
                            key={group}
                            values={values as RetentionSettings}
                        />
                    )}
                </div>
            </AdminPage>
        </>
    );
}

EditSettings.layout = {
    breadcrumbs: [{ title: 'Settings', href: SettingsController.index() }],
};
