import { Head, router, useForm } from '@inertiajs/react';
import IntegrationController from '@/actions/App/Http/Controllers/Admin/IntegrationController';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { Field, SelectField, TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { Option } from '@/types';

type IntegrationFieldData = {
    key: string;
    label: string;
    type: 'secret' | 'text' | 'choice' | 'list' | 'toggle';
    description: string | null;
    options: Option[];
    source: 'saved' | 'env' | 'unset';
    // Always null for a secret: the server never sends one back.
    value: string | boolean | null;
};

type IntegrationData = {
    key: string;
    label: string;
    description: string;
    paystack_mode: 'test' | 'live' | null;
};

const SOURCES = {
    saved: { label: 'Saved here', variant: 'default' },
    env: { label: 'From the server', variant: 'secondary' },
    unset: { label: 'Not set', variant: 'outline' },
} as const;

/**
 * The keys of each third-party service (M12B.15, D46): pick one, then edit its fields, rendered
 * from the server's definitions. A saved value overrides the server's env value; removing it
 * falls back to env.
 */
export default function EditIntegration({
    integration,
    integrations,
    fields,
    unreadable,
}: {
    integration: IntegrationData;
    integrations: Option[];
    fields: IntegrationFieldData[];
    unreadable: boolean;
}) {
    return (
        <>
            <Head title={`${integration.label} integration`} />
            <AdminPage>
                <PageHeader
                    title="Integrations"
                    description="Keys and settings for the services the app connects to. Changes apply straight away, including to background jobs."
                />
                <div className="grid max-w-4xl gap-4">
                    <SelectField
                        name="integration"
                        label="Integration"
                        className="max-w-sm"
                        options={integrations}
                        value={integration.key}
                        onChange={(event) =>
                            router.visit(
                                IntegrationController.edit(event.target.value),
                            )
                        }
                    />
                    {unreadable && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                Saved keys cannot be read with the current
                                APP_KEY, so the server&apos;s values are in use
                                and nothing can be saved. Restore the previous
                                key as APP_PREVIOUS_KEYS.
                            </AlertDescription>
                        </Alert>
                    )}
                    {/* The key resets the inputs after a save or a switch. Fields hold no secrets. */}
                    <IntegrationForm
                        key={`${integration.key}:${JSON.stringify(fields)}`}
                        integration={integration}
                        fields={fields}
                        disabled={unreadable}
                    />
                </div>
            </AdminPage>
        </>
    );
}

function IntegrationForm({
    integration,
    fields,
    disabled,
}: {
    integration: IntegrationData;
    fields: IntegrationFieldData[];
    disabled: boolean;
}) {
    const form = useForm<{
        credentials: Record<string, string | boolean>;
        clear: string[];
    }>({
        credentials: Object.fromEntries(
            fields.map((field) => [field.key, field.value ?? '']),
        ),
        clear: [],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setValue = (key: string, value: string | boolean) =>
        form.setData('credentials', { ...form.data.credentials, [key]: value });

    const setClear = (key: string, clear: boolean) =>
        form.setData(
            'clear',
            clear
                ? [...form.data.clear, key]
                : form.data.clear.filter((item) => item !== key),
        );

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(IntegrationController.update.url(integration.key), {
                    preserveScroll: true,
                });
            }}
        >
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        {integration.label}
                        {integration.paystack_mode && (
                            <Badge variant="outline">
                                {integration.paystack_mode === 'live'
                                    ? 'Live keys'
                                    : 'Test keys'}
                            </Badge>
                        )}
                    </CardTitle>
                    <CardDescription>{integration.description}</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6">
                    {errors.credentials && (
                        <p role="alert" className="text-sm text-destructive">
                            {errors.credentials}
                        </p>
                    )}
                    {fields.map((field) => {
                        const cleared = form.data.clear.includes(field.key);

                        return (
                            <div key={field.key} className="grid gap-2">
                                <IntegrationInput
                                    field={field}
                                    value={form.data.credentials[field.key]}
                                    error={errors[`credentials.${field.key}`]}
                                    disabled={disabled || cleared}
                                    onChange={(value) =>
                                        setValue(field.key, value)
                                    }
                                />
                                {field.source === 'saved' && (
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id={`clear-${field.key}`}
                                            checked={cleared}
                                            disabled={disabled}
                                            onCheckedChange={(checked) =>
                                                setClear(
                                                    field.key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`clear-${field.key}`}
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            Remove the saved value and use the
                                            server&apos;s
                                        </Label>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={disabled || form.processing || !form.isDirty}
                    >
                        Save {integration.label}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function IntegrationInput({
    field,
    value,
    error,
    disabled,
    onChange,
}: {
    field: IntegrationFieldData;
    value: string | boolean;
    error?: string;
    disabled: boolean;
    onChange: (value: string | boolean) => void;
}) {
    const source = SOURCES[field.source];
    const label = (
        <span className="flex items-center gap-2">
            {field.label}
            <Badge variant={source.variant}>{source.label}</Badge>
        </span>
    );
    const name = `credentials[${field.key}]`;

    if (field.type === 'toggle') {
        return (
            <Field label={label} error={error}>
                <ToggleRow
                    id={field.key}
                    label="Turn on"
                    description={field.description ?? undefined}
                    checked={value === true}
                    disabled={disabled}
                    onChange={onChange}
                />
            </Field>
        );
    }

    if (field.type === 'choice') {
        return (
            <SelectField
                name={name}
                label={label}
                options={field.options}
                placeholder={field.source === 'unset' ? 'Choose…' : undefined}
                value={String(value)}
                error={error}
                description={field.description}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
        );
    }

    const secret = field.type === 'secret';

    return (
        <TextField
            name={name}
            label={label}
            type={secret ? 'password' : 'text'}
            autoComplete={secret ? 'new-password' : 'off'}
            placeholder={
                secret && field.source !== 'unset'
                    ? 'Leave blank to keep the current value'
                    : undefined
            }
            value={String(value)}
            error={error}
            description={field.description}
            disabled={disabled}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}
