import { useForm } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { SelectField, TextField } from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Option } from '@/types';

export type PricingSettings = {
    base_currency: string;
    reservation_percent_bp: number;
    reservation_rounding: string;
    day_counting: string;
    daytime_window_start: string;
    daytime_window_end: string;
    rolling_grace_minutes: number;
    hold_minutes: number;
    draft_expiry_hours: number;
    turnaround_minutes: number;
    default_mileage_km: number;
    unlimited_mileage_after_days: number | null;
};

export type PricingSettingsContext = {
    roundingOptions: Option[];
    dayCountingOptions: Option[];
};

export function PricingSettingsForm({
    values,
    context,
}: {
    values: PricingSettings;
    context: PricingSettingsContext;
}) {
    const form = useForm({
        reservation_percent: String(values.reservation_percent_bp / 100),
        reservation_rounding: values.reservation_rounding,
        day_counting: values.day_counting,
        daytime_window_start: values.daytime_window_start,
        daytime_window_end: values.daytime_window_end,
        rolling_grace_minutes: String(values.rolling_grace_minutes),
        hold_minutes: String(values.hold_minutes),
        draft_expiry_hours: String(values.draft_expiry_hours),
        turnaround_minutes: String(values.turnaround_minutes),
        default_mileage_km: String(values.default_mileage_km),
        unlimited_mileage_after_days:
            values.unlimited_mileage_after_days === null
                ? ''
                : String(values.unlimited_mileage_after_days),
    });

    const field = (key: keyof typeof form.data) => ({
        name: key,
        value: form.data[key],
        error: form.errors[key],
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
        ) => form.setData(key, event.target.value),
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    unlimited_mileage_after_days:
                        data.unlimited_mileage_after_days || null,
                }));
                form.put(SettingsController.update.url('pricing'), {
                    preserveScroll: true,
                });
            }}
            className="grid gap-6"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Quotes</CardTitle>
                    <CardDescription>
                        Prices are set and charged in {values.base_currency}.
                        Try a change in the Quote sandbox before saving it.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        label="Reservation (% of the total)"
                        inputMode="decimal"
                        required
                        description="Paid to confirm the booking; the rest is due at handover."
                        {...field('reservation_percent')}
                    />
                    <SelectField
                        label="Reservation rounding"
                        options={context.roundingOptions}
                        {...field('reservation_rounding')}
                    />
                    <SelectField
                        label="Billable days"
                        options={context.dayCountingOptions}
                        className="sm:col-span-2"
                        {...field('day_counting')}
                    />
                    {form.data.day_counting === 'daytime_blocks' && (
                        <>
                            <TextField
                                label="Daytime window starts"
                                type="time"
                                required
                                {...field('daytime_window_start')}
                            />
                            <TextField
                                label="Daytime window ends"
                                type="time"
                                required
                                {...field('daytime_window_end')}
                            />
                        </>
                    )}
                    {form.data.day_counting === 'rolling_24h' && (
                        <TextField
                            label="Grace before an extra day (minutes)"
                            type="number"
                            min={0}
                            max={720}
                            required
                            {...field('rolling_grace_minutes')}
                        />
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Mileage</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        label="Allowance (km per day)"
                        type="number"
                        min={1}
                        required
                        description="Used for cars without their own allowance."
                        {...field('default_mileage_km')}
                    />
                    <TextField
                        label="Unlimited from (days)"
                        type="number"
                        min={1}
                        description="Leave empty to always apply the allowance."
                        {...field('unlimited_mileage_after_days')}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Booking timings</CardTitle>
                    <CardDescription>
                        Used by the booking flow (M8).
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-3">
                    <TextField
                        label="Hold while paying (minutes)"
                        type="number"
                        min={5}
                        required
                        {...field('hold_minutes')}
                    />
                    <TextField
                        label="Unfinished bookings expire (hours)"
                        type="number"
                        min={1}
                        required
                        {...field('draft_expiry_hours')}
                    />
                    <TextField
                        label="Turnaround between rentals (minutes)"
                        type="number"
                        min={0}
                        required
                        {...field('turnaround_minutes')}
                    />
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save pricing settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
