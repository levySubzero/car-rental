import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { MediaUploader } from '@/components/media/media-uploader';
import { Field, inputClass } from '@/components/public/form-field';
import { Button } from '@/components/ui/button';
import { driver as saveDriver } from '@/routes/bookings';
import type { Booking, DocumentOptions, MediaItem } from '@/types';

/**
 * Step 2, the main driver and their documents (M8.11, M8.12). Documents upload straight to
 * the private booking collections with the booking token; saving the step attaches them.
 * Which documents are asked for follows the id type and residency (Settings → Booking).
 */
export function DriverForm({
    token,
    booking,
    documents: options,
}: {
    token: string;
    booking: Booking;
    documents: DocumentOptions;
}) {
    const saved = booking.driver ?? null;
    const form = useForm({
        first_name: saved?.first_name ?? '',
        second_name: saved?.second_name ?? '',
        surname: saved?.surname ?? '',
        dob: saved?.dob ?? '',
        email: saved?.email ?? '',
        phone: saved?.phone ?? '',
        nationality: saved?.nationality ?? 'Kenyan',
        is_kenyan_resident: saved?.is_kenyan_resident ?? true,
        id_type: saved?.id_type ?? 'national_id',
        id_number: '',
        kra_pin: '',
        licence_number: '',
        licence_expiry: saved?.licence_expiry ?? '',
        licence_years_declared: saved
            ? String(saved.licence_years_declared)
            : '',
        documents: Object.fromEntries(
            options.documents.map((document) => [
                document.value,
                booking.documents?.[document.value] ?? null,
            ]),
        ) as Record<string, MediaItem | null>,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const rule =
        options.requirements[form.data.id_type]?.[
            form.data.is_kenyan_resident ? 'resident' : 'non_resident'
        ];
    const required = options.documents.filter((document) =>
        rule?.documents.includes(document.value),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            documents: Object.fromEntries(
                Object.entries(data.documents).map(([key, file]) => [
                    key,
                    file?.uuid ?? null,
                ]),
            ),
        }));
        form.put(saveDriver(token).url, { preserveScroll: true });
    };

    const text = (
        key: Exclude<
            keyof typeof form.data,
            'documents' | 'is_kenyan_resident'
        >,
    ) => ({
        value: form.data[key],
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
        ) => form.setData(key, event.target.value),
    });

    const keepHint = (hint: string | null | undefined) =>
        hint ? `Saved as ${hint}. Leave blank to keep it.` : undefined;

    return (
        <form onSubmit={submit} noValidate className="grid max-w-3xl gap-10">
            <fieldset className="grid gap-5 sm:grid-cols-3">
                <legend className="mb-4 text-lg font-semibold text-foreground">
                    Main driver
                </legend>
                <Field label="First name" error={errors.first_name}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            required
                            autoComplete="given-name"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('first_name')}
                        />
                    )}
                </Field>
                <Field
                    label="Second name (optional)"
                    error={errors.second_name}
                >
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            autoComplete="additional-name"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('second_name')}
                        />
                    )}
                </Field>
                <Field label="Surname" error={errors.surname}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            required
                            autoComplete="family-name"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('surname')}
                        />
                    )}
                </Field>
                <Field label="Date of birth" error={errors.dob}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            type="date"
                            required
                            autoComplete="bday"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('dob')}
                        />
                    )}
                </Field>
                <Field label="Email" error={errors.email}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            type="email"
                            required
                            autoComplete="email"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('email')}
                        />
                    )}
                </Field>
                <Field label="Phone or WhatsApp" error={errors.phone}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            type="tel"
                            required
                            autoComplete="tel"
                            placeholder="+254 7…"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('phone')}
                        />
                    )}
                </Field>
            </fieldset>

            <fieldset className="grid gap-5 sm:grid-cols-3">
                <legend className="mb-4 text-lg font-semibold text-foreground">
                    Identity
                </legend>
                <Field label="Nationality" error={errors.nationality}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            required
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('nationality')}
                        />
                    )}
                </Field>
                <Field
                    label="Do you live in Kenya?"
                    error={errors.is_kenyan_resident}
                >
                    {(id, describedBy, invalid) => (
                        <select
                            id={id}
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            value={form.data.is_kenyan_resident ? '1' : '0'}
                            onChange={(event) =>
                                form.setData(
                                    'is_kenyan_resident',
                                    event.target.value === '1',
                                )
                            }
                        >
                            <option value="1">Yes, I live in Kenya</option>
                            <option value="0">No, I am visiting</option>
                        </select>
                    )}
                </Field>
                <Field label="Identity document" error={errors.id_type}>
                    {(id, describedBy, invalid) => (
                        <select
                            id={id}
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('id_type')}
                        >
                            {options.id_types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    )}
                </Field>
                <Field
                    label={
                        form.data.id_type === 'passport'
                            ? 'Passport number'
                            : 'ID number'
                    }
                    id="driver-id-number"
                    error={errors.id_number}
                    hint={keepHint(saved?.id_number_hint)}
                >
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            required={!saved}
                            autoComplete="off"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('id_number')}
                        />
                    )}
                </Field>
                {rule?.kra_pin !== 'hidden' && (
                    <Field
                        label={
                            rule?.kra_pin === 'required'
                                ? 'KRA PIN'
                                : 'KRA PIN (optional)'
                        }
                        error={errors.kra_pin}
                        hint={
                            keepHint(saved?.kra_pin_hint) ??
                            'A letter, nine digits and a letter'
                        }
                    >
                        {(id, describedBy, invalid) => (
                            <input
                                id={id}
                                autoComplete="off"
                                placeholder="A123456789Z"
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                className={inputClass}
                                {...text('kra_pin')}
                            />
                        )}
                    </Field>
                )}
            </fieldset>

            <fieldset className="grid gap-5 sm:grid-cols-3">
                <legend className="mb-4 text-lg font-semibold text-foreground">
                    Driving licence
                </legend>
                <Field
                    label="Licence number"
                    error={errors.licence_number}
                    hint={keepHint(saved?.licence_number_hint)}
                >
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            required={!saved}
                            autoComplete="off"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('licence_number')}
                        />
                    )}
                </Field>
                <Field label="Expiry date" error={errors.licence_expiry}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            type="date"
                            required
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('licence_expiry')}
                        />
                    )}
                </Field>
                <Field label="Years held" error={errors.licence_years_declared}>
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            type="number"
                            min={0}
                            max={70}
                            required
                            inputMode="numeric"
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={inputClass}
                            {...text('licence_years_declared')}
                        />
                    )}
                </Field>
            </fieldset>

            <fieldset className="grid gap-6">
                <legend className="mb-2 text-lg font-semibold text-foreground">
                    Documents
                </legend>
                <p className="-mt-2 text-sm text-muted-foreground">
                    Clear photos or PDF scans. They are stored privately and
                    only our bookings team can open them.
                </p>
                {required.map((document) => {
                    const file = form.data.documents[document.value];

                    return (
                        <div key={document.value} className="grid gap-2">
                            <p className="text-sm font-medium text-white/85">
                                {document.label}
                            </p>
                            <MediaUploader
                                limits={document.limits}
                                value={file ? [file] : []}
                                onChange={(items) =>
                                    // Uploads finish at their own pace: merge into the latest data.
                                    form.setData((data) => ({
                                        ...data,
                                        documents: {
                                            ...data.documents,
                                            [document.value]: items[0] ?? null,
                                        },
                                    }))
                                }
                                bookingToken={token}
                                error={errors[`documents.${document.value}`]}
                            />
                        </div>
                    );
                })}
            </fieldset>

            <div>
                <Button
                    type="submit"
                    size="lg"
                    disabled={form.processing}
                    className="h-12 px-8 font-semibold"
                >
                    {form.processing ? 'Saving…' : 'Save and review'}
                </Button>
            </div>
        </form>
    );
}
