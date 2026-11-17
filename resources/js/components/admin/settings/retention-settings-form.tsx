import { useForm } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { TextField } from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export type RetentionSettings = {
    kyc_retention_months: number;
    draft_retention_days: number;
    activity_log_retention_months: number;
    mail_log_retention_days: number;
};

export function RetentionSettingsForm({
    values,
}: {
    values: RetentionSettings;
}) {
    const form = useForm({
        kyc_retention_months: String(values.kyc_retention_months),
        draft_retention_days: String(values.draft_retention_days),
        activity_log_retention_months: String(
            values.activity_log_retention_months,
        ),
        mail_log_retention_days: String(values.mail_log_retention_days),
    });

    const field = (key: keyof typeof form.data) => ({
        name: key,
        type: 'number',
        min: 1,
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
                form.put(SettingsController.update.url('retention'), {
                    preserveScroll: true,
                });
            }}
        >
            <Card>
                <CardHeader>
                    <CardTitle>Data retention</CardTitle>
                    <CardDescription>
                        How long personal data is kept (Kenya Data Protection
                        Act 2019). Files on legal hold are never purged.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <TextField
                        label="Identity documents (months after completion)"
                        max={120}
                        {...field('kyc_retention_months')}
                    />
                    <TextField
                        label="Unfinished bookings (days)"
                        max={90}
                        {...field('draft_retention_days')}
                    />
                    <TextField
                        label="Activity log (months)"
                        max={120}
                        {...field('activity_log_retention_months')}
                    />
                    <TextField
                        label="Email log (days)"
                        max={365}
                        {...field('mail_log_retention_days')}
                    />
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save retention settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
