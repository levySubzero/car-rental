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

export type FxSettings = {
    provider: string;
    refresh_minutes: number;
    margin_minor: number;
    stale_after_hours: number;
    max_jump_percent: number;
};

export type FxSettingsContext = {
    providers: Option[];
    hasApiKey: boolean;
};

const marginModes: Option[] = [
    { value: 'below', label: 'Below the market rate' },
    { value: 'above', label: 'Above the market rate' },
    { value: 'exact', label: 'Exactly the market rate' },
];

export function FxSettingsForm({
    values,
    context,
}: {
    values: FxSettings;
    context: FxSettingsContext;
}) {
    const form = useForm({
        provider: values.provider,
        refresh_minutes: String(values.refresh_minutes),
        // Stored signed in cents (positive is below market); edited as a mode and KES amount.
        margin_mode:
            values.margin_minor === 0
                ? 'exact'
                : values.margin_minor > 0
                  ? 'below'
                  : 'above',
        margin_amount:
            values.margin_minor === 0
                ? ''
                : String(Math.abs(values.margin_minor) / 100),
        stale_after_hours: String(values.stale_after_hours),
        max_jump_percent: String(values.max_jump_percent),
    });

    const number = (key: keyof typeof form.data) => ({
        name: key,
        type: 'number',
        required: true,
        value: form.data[key],
        error: form.errors[key],
        onChange: (event: React.ChangeEvent<HTMLInputElement>) =>
            form.setData(key, event.target.value),
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(SettingsController.update.url('fx'), {
                    preserveScroll: true,
                });
            }}
        >
            <Card>
                <CardHeader>
                    <CardTitle>Exchange rates</CardTitle>
                    <CardDescription>
                        Where rates come from and how display prices are worked
                        out from the market rate and your offset.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <SelectField
                        name="provider"
                        label="Provider"
                        options={context.providers}
                        value={form.data.provider}
                        error={form.errors.provider}
                        description={
                            form.data.provider !== 'manual' &&
                            !context.hasApiKey
                                ? 'No API key is set under Integrations or on the server, so nothing will be fetched.'
                                : 'The API key is set under Integrations or on the server, not here.'
                        }
                        onChange={(event) =>
                            form.setData('provider', event.target.value)
                        }
                    />
                    <SelectField
                        name="margin_mode"
                        label="Display rate"
                        options={marginModes}
                        value={form.data.margin_mode}
                        error={form.errors.margin_mode}
                        description="Applies to prices shown in other currencies. Charges stay in KES."
                        onChange={(event) =>
                            form.setData('margin_mode', event.target.value)
                        }
                    />
                    {form.data.margin_mode !== 'exact' && (
                        <TextField
                            label="Amount (KES per unit)"
                            inputMode="decimal"
                            description={`E.g. 2 shows a market rate of 129 as ${form.data.margin_mode === 'below' ? '127' : '131'}.`}
                            {...number('margin_amount')}
                            type="text"
                        />
                    )}
                    <TextField
                        label="Fetch every (minutes)"
                        min={15}
                        max={1440}
                        {...number('refresh_minutes')}
                    />
                    <TextField
                        label="Stale after (hours)"
                        min={1}
                        max={168}
                        description="After this, a manual rate takes over, or the dashboard warns."
                        {...number('stale_after_hours')}
                    />
                    <TextField
                        label="Hold back moves over (%)"
                        min={1}
                        max={100}
                        description="A fetched rate that moved more than this waits for review."
                        {...number('max_jump_percent')}
                    />
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save exchange rate settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
