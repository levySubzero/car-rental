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
        // Stored in cents; edited in KES.
        margin: String(values.margin_minor / 100),
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
                        out: KES amount ÷ (market rate − margin).
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
                                ? 'FX_API_KEY is not set on the server, so nothing will be fetched.'
                                : 'The API key is set on the server (FX_API_KEY), not here.'
                        }
                        onChange={(event) =>
                            form.setData('provider', event.target.value)
                        }
                    />
                    <TextField
                        label="Margin (KES per unit)"
                        inputMode="decimal"
                        description="Subtracted from the market rate for display prices."
                        {...number('margin')}
                        type="text"
                    />
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
