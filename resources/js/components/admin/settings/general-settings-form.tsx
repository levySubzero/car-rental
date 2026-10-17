import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { TextareaField, TextField } from '@/components/admin/form-field';
import { StringListField } from '@/components/admin/list-field';
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
import { Input } from '@/components/ui/input';
import { humanize } from '@/lib/format';

export type GeneralSettings = {
    company_name: string;
    legal_name: string | null;
    phones: string[];
    whatsapp_number: string | null;
    email: string | null;
    address: string | null;
    map_embed_url: string | null;
    latitude: number | null;
    longitude: number | null;
    socials: Record<string, string>;
    opening_hours: { days: string; hours: string }[];
    whatsapp_templates: Record<string, string>;
};

const PLATFORMS = [
    'facebook',
    'instagram',
    'tiktok',
    'x',
    'youtube',
    'linkedin',
];

const TEMPLATES: Record<string, string> = {
    car_enquiry: 'Car enquiry',
    general_enquiry: 'General enquiry',
    booking: 'About a booking',
};

export function GeneralSettingsForm({ values }: { values: GeneralSettings }) {
    const form = useForm({
        company_name: values.company_name,
        legal_name: values.legal_name ?? '',
        phones: values.phones,
        whatsapp_number: values.whatsapp_number ?? '',
        email: values.email ?? '',
        address: values.address ?? '',
        map_embed_url: values.map_embed_url ?? '',
        latitude: values.latitude?.toString() ?? '',
        longitude: values.longitude?.toString() ?? '',
        socials: Object.fromEntries(
            PLATFORMS.map((platform) => [
                platform,
                values.socials[platform] ?? '',
            ]),
        ),
        opening_hours: values.opening_hours,
        whatsapp_templates: values.whatsapp_templates,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const text = (
        key:
            | 'company_name'
            | 'legal_name'
            | 'whatsapp_number'
            | 'email'
            | 'address'
            | 'map_embed_url'
            | 'latitude'
            | 'longitude',
    ) => ({
        name: key,
        value: form.data[key],
        error: errors[key],
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
        ) => form.setData(key, event.target.value),
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(SettingsController.update.url('general'), {
                    preserveScroll: true,
                });
            }}
            className="grid gap-6"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Company</CardTitle>
                    <CardDescription>
                        Shown in the site header, footer, emails and vouchers.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        label="Trading name"
                        required
                        {...text('company_name')}
                    />
                    <TextField label="Legal name" {...text('legal_name')} />
                    <TextField label="Email" type="email" {...text('email')} />
                    <TextField
                        label="WhatsApp number"
                        placeholder="+254 7XX XXX XXX"
                        {...text('whatsapp_number')}
                    />
                    <div className="sm:col-span-2">
                        <StringListField
                            name="phones"
                            label="Phone numbers"
                            value={form.data.phones}
                            onChange={(phones) =>
                                form.setData('phones', phones)
                            }
                            errors={errors}
                            max={5}
                            addLabel="Add phone"
                            placeholder="+254 7XX XXX XXX"
                            type="tel"
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Location</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <TextareaField
                        label="Address"
                        rows={3}
                        className="sm:col-span-2"
                        {...text('address')}
                    />
                    <TextField
                        label="Google Maps embed URL"
                        className="sm:col-span-2"
                        placeholder="https://www.google.com/maps/embed?..."
                        {...text('map_embed_url')}
                    />
                    <TextField
                        label="Latitude"
                        inputMode="decimal"
                        {...text('latitude')}
                    />
                    <TextField
                        label="Longitude"
                        inputMode="decimal"
                        {...text('longitude')}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Opening hours</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {form.data.opening_hours.map((row, index) => (
                        <div key={index} className="grid gap-1">
                            <div className="flex gap-2">
                                <Input
                                    aria-label="Days"
                                    placeholder="Mon – Fri"
                                    value={row.days}
                                    onChange={(event) =>
                                        form.setData(
                                            'opening_hours',
                                            form.data.opening_hours.map(
                                                (r, i) =>
                                                    i === index
                                                        ? {
                                                              ...r,
                                                              days: event.target
                                                                  .value,
                                                          }
                                                        : r,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label="Hours"
                                    placeholder="08:00 – 18:00"
                                    value={row.hours}
                                    onChange={(event) =>
                                        form.setData(
                                            'opening_hours',
                                            form.data.opening_hours.map(
                                                (r, i) =>
                                                    i === index
                                                        ? {
                                                              ...r,
                                                              hours: event
                                                                  .target.value,
                                                          }
                                                        : r,
                                            ),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove"
                                    onClick={() =>
                                        form.setData(
                                            'opening_hours',
                                            form.data.opening_hours.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            </div>
                            <InputError
                                message={
                                    errors[`opening_hours.${index}.days`] ??
                                    errors[`opening_hours.${index}.hours`]
                                }
                            />
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="justify-self-start"
                        onClick={() =>
                            form.setData('opening_hours', [
                                ...form.data.opening_hours,
                                { days: '', hours: '' },
                            ])
                        }
                    >
                        <Plus /> Add hours
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Social links</CardTitle>
                    <CardDescription>
                        Leave blank to hide a platform.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    {PLATFORMS.map((platform) => (
                        <TextField
                            key={platform}
                            name={`socials.${platform}`}
                            label={platform === 'x' ? 'X' : humanize(platform)}
                            type="url"
                            placeholder="https://"
                            value={form.data.socials[platform]}
                            error={errors[`socials.${platform}`]}
                            onChange={(event) =>
                                form.setData('socials', {
                                    ...form.data.socials,
                                    [platform]: event.target.value,
                                })
                            }
                        />
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>WhatsApp messages</CardTitle>
                    <CardDescription>
                        Prefilled text for WhatsApp buttons. Use {'{car}'},{' '}
                        {'{dates}'} and {'{reference}'} as placeholders.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    {Object.entries(TEMPLATES).map(([key, label]) => (
                        <TextareaField
                            key={key}
                            name={`whatsapp_templates.${key}`}
                            label={label}
                            rows={2}
                            value={form.data.whatsapp_templates[key] ?? ''}
                            error={errors[`whatsapp_templates.${key}`]}
                            onChange={(event) =>
                                form.setData('whatsapp_templates', {
                                    ...form.data.whatsapp_templates,
                                    [key]: event.target.value,
                                })
                            }
                        />
                    ))}
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save general settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
