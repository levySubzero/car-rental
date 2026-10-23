import { useForm } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { NativeSelect, TextField } from '@/components/admin/form-field';
import InputError from '@/components/input-error';
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
import { Switch } from '@/components/ui/switch';
import type { Option } from '@/types';

type Residency = 'resident' | 'non_resident';

type DocumentRule = { documents: string[]; kra_pin: string };

export type BookingSettings = {
    min_licence_years: number;
    licence_must_outlive_return: boolean;
    flight_notice_days: number;
    document_requirements: Record<string, Record<Residency, DocumentRule>>;
};

export type BookingSettingsContext = {
    idTypes: Option[];
    documents: Option[];
    kraPinOptions: Option[];
};

const RESIDENCIES: { value: Residency; label: string }[] = [
    { value: 'resident', label: 'Kenyan resident' },
    { value: 'non_resident', label: 'Visitor' },
];

export function BookingSettingsForm({
    values,
    context,
}: {
    values: BookingSettings;
    context: BookingSettingsContext;
}) {
    const form = useForm({
        min_licence_years: String(values.min_licence_years),
        licence_must_outlive_return: values.licence_must_outlive_return,
        flight_notice_days: String(values.flight_notice_days),
        document_requirements: values.document_requirements,
    });

    const errors = form.errors as Record<string, string | undefined>;

    const rule = (idType: string, residency: Residency): DocumentRule =>
        form.data.document_requirements[idType]?.[residency] ?? {
            documents: [],
            kra_pin: 'optional',
        };

    const setRule = (
        idType: string,
        residency: Residency,
        next: DocumentRule,
    ) =>
        form.setData('document_requirements', {
            ...form.data.document_requirements,
            [idType]: {
                ...form.data.document_requirements[idType],
                [residency]: next,
            },
        });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(SettingsController.update.url('booking'), {
                    preserveScroll: true,
                });
            }}
            className="grid gap-6"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Driver rules</CardTitle>
                    <CardDescription>
                        Age limits come from the deposit bands. These rules
                        apply to the main driver.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        label="Minimum years holding a licence"
                        type="number"
                        min={0}
                        max={30}
                        required
                        name="min_licence_years"
                        value={form.data.min_licence_years}
                        error={form.errors.min_licence_years}
                        onChange={(event) =>
                            form.setData(
                                'min_licence_years',
                                event.target.value,
                            )
                        }
                    />
                    <TextField
                        label="Notice for airport pickups (days)"
                        type="number"
                        min={0}
                        max={30}
                        required
                        description="Renters who give a flight number with less notice see a warning; the booking still goes through."
                        name="flight_notice_days"
                        value={form.data.flight_notice_days}
                        error={form.errors.flight_notice_days}
                        onChange={(event) =>
                            form.setData(
                                'flight_notice_days',
                                event.target.value,
                            )
                        }
                    />
                    <div className="flex items-start justify-between gap-4 sm:col-span-2">
                        <div className="grid gap-1">
                            <Label htmlFor="licence_must_outlive_return">
                                Licence must be valid until the return date
                            </Label>
                            <InputError
                                message={
                                    form.errors.licence_must_outlive_return
                                }
                            />
                        </div>
                        <Switch
                            id="licence_must_outlive_return"
                            checked={form.data.licence_must_outlive_return}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'licence_must_outlive_return',
                                    checked,
                                )
                            }
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Documents</CardTitle>
                    <CardDescription>
                        What the driver uploads, by the identity document they
                        book with and whether they live in Kenya.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6 lg:grid-cols-2">
                    {context.idTypes.map((idType) =>
                        RESIDENCIES.map((residency) => {
                            const current = rule(idType.value, residency.value);
                            const key = `document_requirements.${idType.value}.${residency.value}`;

                            return (
                                <fieldset
                                    key={key}
                                    className="grid gap-3 rounded-md border p-4"
                                >
                                    <legend className="px-1 text-sm font-medium">
                                        {idType.label}, {residency.label}
                                    </legend>
                                    {context.documents.map((document) => {
                                        const id = `${key}.${document.value}`;

                                        return (
                                            <div
                                                key={document.value}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={id}
                                                    checked={current.documents.includes(
                                                        document.value,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setRule(
                                                            idType.value,
                                                            residency.value,
                                                            {
                                                                ...current,
                                                                documents:
                                                                    checked
                                                                        ? [
                                                                              ...current.documents,
                                                                              document.value,
                                                                          ]
                                                                        : current.documents.filter(
                                                                              (
                                                                                  value,
                                                                              ) =>
                                                                                  value !==
                                                                                  document.value,
                                                                          ),
                                                            },
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={id}
                                                    className="font-normal"
                                                >
                                                    {document.label}
                                                </Label>
                                            </div>
                                        );
                                    })}
                                    <InputError
                                        message={errors[`${key}.documents`]}
                                    />
                                    <div className="grid gap-1.5">
                                        <Label htmlFor={`${key}.kra_pin`}>
                                            KRA PIN
                                        </Label>
                                        <NativeSelect
                                            id={`${key}.kra_pin`}
                                            options={context.kraPinOptions}
                                            value={current.kra_pin}
                                            onChange={(event) =>
                                                setRule(
                                                    idType.value,
                                                    residency.value,
                                                    {
                                                        ...current,
                                                        kra_pin:
                                                            event.target.value,
                                                    },
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors[`${key}.kra_pin`]}
                                        />
                                    </div>
                                </fieldset>
                            );
                        }),
                    )}
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save booking settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
