import type { ComponentProps, ReactNode } from 'react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/*
 * Uncontrolled form fields for Inertia's <Form>: every field renders a named
 * input, so the form submits without extra state.
 */

export function Field({
    label,
    htmlFor,
    error,
    description,
    className,
    children,
}: {
    label: ReactNode;
    htmlFor?: string;
    error?: string;
    description?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {description && !error && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

type FieldProps = {
    name: string;
    label: ReactNode;
    error?: string;
    description?: ReactNode;
    className?: string;
};

export function TextField({
    name,
    label,
    error,
    description,
    className,
    ...input
}: FieldProps & Omit<ComponentProps<typeof Input>, 'name'>) {
    const id = useId();

    return (
        <Field
            label={label}
            htmlFor={id}
            error={error}
            description={description}
            className={className}
        >
            <Input id={id} name={name} aria-invalid={!!error} {...input} />
        </Field>
    );
}

export function TextareaField({
    name,
    label,
    error,
    description,
    className,
    ...textarea
}: FieldProps & Omit<ComponentProps<typeof Textarea>, 'name'>) {
    const id = useId();

    return (
        <Field
            label={label}
            htmlFor={id}
            error={error}
            description={description}
            className={className}
        >
            <Textarea
                id={id}
                name={name}
                aria-invalid={!!error}
                {...textarea}
            />
        </Field>
    );
}

export function SelectField({
    name,
    label,
    error,
    description,
    className,
    options,
    placeholder,
    ...select
}: FieldProps &
    Omit<ComponentProps<'select'>, 'name'> & {
        options: { value: string; label: string }[];
        placeholder?: string;
    }) {
    const id = useId();

    return (
        <Field
            label={label}
            htmlFor={id}
            error={error}
            description={description}
            className={className}
        >
            <NativeSelect
                id={id}
                name={name}
                aria-invalid={!!error}
                options={options}
                placeholder={placeholder}
                {...select}
            />
        </Field>
    );
}

export function NativeSelect({
    options,
    placeholder,
    className,
    ...select
}: ComponentProps<'select'> & {
    options: { value: string; label: string }[];
    placeholder?: string;
}) {
    return (
        <select
            className={cn(
                'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30',
                className,
            )}
            {...select}
        >
            {placeholder !== undefined && (
                <option value="">{placeholder}</option>
            )}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

/**
 * Submits "1" or "0" under `name`, so unchecked switches are sent too.
 */
export function SwitchField({
    name,
    label,
    error,
    description,
    className,
    defaultChecked = false,
}: FieldProps & { defaultChecked?: boolean }) {
    const id = useId();
    const [checked, setChecked] = useState(defaultChecked);

    return (
        <div
            className={cn('flex items-start justify-between gap-4', className)}
        >
            <div className="grid gap-1">
                <Label htmlFor={id}>{label}</Label>
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
                <InputError message={error} />
            </div>
            <input type="hidden" name={name} value={checked ? '1' : '0'} />
            <Switch id={id} checked={checked} onCheckedChange={setChecked} />
        </div>
    );
}

/**
 * Money is typed in major units (e.g. 15000.00); the server converts to minor units.
 */
export function MoneyField({
    currency = 'KES',
    ...props
}: FieldProps &
    Omit<ComponentProps<typeof Input>, 'name' | 'type'> & {
        currency?: string;
    }) {
    const { name, label, error, description, className, ...input } = props;
    const id = useId();

    return (
        <Field
            label={label}
            htmlFor={id}
            error={error}
            description={description}
            className={className}
        >
            <div className="relative">
                <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                    {currency}
                </span>
                <Input
                    id={id}
                    name={name}
                    inputMode="decimal"
                    pattern="^\d+(\.\d{1,2})?$"
                    className="pl-12 tabular-nums"
                    aria-invalid={!!error}
                    {...input}
                />
            </div>
        </Field>
    );
}

export function DateField(
    props: FieldProps & Omit<ComponentProps<typeof Input>, 'name' | 'type'>,
) {
    return <TextField type="date" {...props} />;
}
