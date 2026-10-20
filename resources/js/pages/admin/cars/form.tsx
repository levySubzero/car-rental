import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import CarController from '@/actions/App/Http/Controllers/Admin/CarController';
import { CarBlackouts } from '@/components/admin/catalog/car-blackouts';
import { SlugField } from '@/components/admin/catalog/slug-field';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import {
    Field,
    MoneyField,
    NativeSelect,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { KeyValueField } from '@/components/admin/key-value-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { MediaUploader } from '@/components/media/media-uploader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCan } from '@/hooks/use-can';
import { metadataFromRows, rowsFromMetadata } from '@/lib/metadata';
import type {
    AdminCar,
    MediaCollectionLimits,
    MediaItem,
    Option,
} from '@/types';

type Props = {
    car: AdminCar | null;
    brandOptions: Option[];
    typeOptions: Option[];
    fuelOptions: Option[];
    transmissionOptions: Option[];
    blackoutReasons: Option[];
    currencies: string[];
    defaultMileageKm: number;
    galleryLimits: MediaCollectionLimits;
};

// Which tab each field lives on, to flag tabs that hold validation errors.
const TAB_FIELDS: Record<string, string[]> = {
    specs: [
        'brand_id',
        'car_type_id',
        'name',
        'slug',
        'model_year',
        'engine_type',
        'engine_capacity_litres',
        'fuel_type',
        'transmission',
        'colour',
        'doors',
        'seats',
        'description',
    ],
    pricing: [
        'daily_rate',
        'car_value',
        'mileage_limit_km',
        'damage_excess',
        'damage_excess_currency',
    ],
    photos: ['gallery', 'cover'],
    extra: ['metadata'],
};

const text = (value: number | string | null | undefined) =>
    value === null || value === undefined ? '' : String(value);

/**
 * Create a car (`car` is null) or edit one. Saving never publishes: publishing is a
 * separate action that needs cars.publish.
 */
export default function CarForm({
    car,
    brandOptions,
    typeOptions,
    fuelOptions,
    transmissionOptions,
    blackoutReasons,
    currencies,
    defaultMileageKm,
    galleryLimits,
}: Props) {
    const can = useCan();
    const editable = car === null ? can('cars.create') : can('cars.update');
    const canSeeValue = can('cars.view-value');
    const [tab, setTab] = useState('specs');
    const [gallery, setGallery] = useState<MediaItem[]>(car?.gallery ?? []);

    const form = useForm({
        brand_id: text(car?.brand_id),
        car_type_id: text(car?.car_type_id),
        name: car?.name ?? '',
        slug: '',
        model_year: text(car?.model_year),
        engine_type: car?.engine_type ?? '',
        engine_capacity_litres: text(car?.engine_capacity_litres),
        fuel_type: car?.fuel_type ?? fuelOptions[0]?.value ?? '',
        transmission: car?.transmission ?? transmissionOptions[0]?.value ?? '',
        colour: car?.colour ?? '',
        doors: text(car?.doors ?? 4),
        seats: text(car?.seats ?? 5),
        daily_rate: car?.daily_rate.major ?? '',
        car_value: car?.car_value?.major ?? '',
        mileage_limit_km: text(car?.mileage_limit_km),
        damage_excess: car?.damage_excess?.major ?? '',
        damage_excess_currency:
            car?.damage_excess?.currency ?? currencies[0] ?? 'KES',
        description: car?.description ?? '',
        gallery: (car?.gallery ?? []).map((item) => item.uuid),
        cover:
            car?.gallery?.find((item) => item.is_primary)?.uuid ??
            (null as string | null),
        metadata_rows: rowsFromMetadata(car?.metadata),
    });

    const errors = form.errors as Record<string, string | undefined>;
    const tabHasError = (name: string) =>
        Object.keys(errors).some((key) =>
            TAB_FIELDS[name].some(
                (field) => key === field || key.startsWith(`${field}.`),
            ),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform(({ metadata_rows, car_value, ...data }) => ({
            ...data,
            ...(canSeeValue ? { car_value } : {}),
            metadata: metadataFromRows(metadata_rows),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => form.setData('slug', ''),
            onError: (fieldErrors: Record<string, string>) => {
                const first = Object.keys(TAB_FIELDS).find((name) =>
                    Object.keys(fieldErrors).some((key) =>
                        TAB_FIELDS[name].some(
                            (field) =>
                                key === field || key.startsWith(`${field}.`),
                        ),
                    ),
                );

                if (first) {
                    setTab(first);
                }
            },
        };

        if (car === null) {
            form.post(CarController.store.url(), options);
        } else {
            form.put(CarController.update.url(car.slug), options);
        }
    };

    const tabTrigger = (value: string, label: string) => (
        <TabsTrigger value={value} className="gap-1.5">
            {label}
            {tabHasError(value) && (
                <span
                    className="size-1.5 rounded-full bg-destructive"
                    aria-label="has errors"
                />
            )}
        </TabsTrigger>
    );

    return (
        <>
            <Head title={car?.name ?? 'New car'} />
            <AdminPage>
                <PageHeader
                    title={car?.name ?? 'New car'}
                    description={
                        car ? (
                            <span className="flex flex-wrap items-center gap-2">
                                {car.is_published ? (
                                    <Badge>Published</Badge>
                                ) : (
                                    <Badge variant="outline">Draft</Badge>
                                )}
                                {!car.is_available && (
                                    <Badge variant="secondary">
                                        Unavailable
                                    </Badge>
                                )}
                                {car.is_featured && (
                                    <Badge variant="secondary">Featured</Badge>
                                )}
                            </span>
                        ) : (
                            'Add the car, then publish it once it has photos.'
                        )
                    }
                    actions={
                        car && <CarActions car={car} dirty={form.isDirty} />
                    }
                />

                <form onSubmit={submit} className="grid max-w-4xl gap-6">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList className="flex-wrap">
                            {tabTrigger('specs', 'Specs')}
                            {tabTrigger('pricing', 'Pricing')}
                            {tabTrigger('photos', 'Photos')}
                            {tabTrigger('extra', 'Extra info')}
                            {car && (
                                <TabsTrigger value="blackouts">
                                    Blackouts
                                </TabsTrigger>
                            )}
                        </TabsList>

                        <TabsContent value="specs" className="mt-4">
                            <Card>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <TextField
                                        name="name"
                                        label="Name"
                                        required
                                        className="sm:col-span-2"
                                        placeholder="Toyota Land Cruiser V8"
                                        disabled={!editable}
                                        value={form.data.name}
                                        error={form.errors.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <div className="sm:col-span-2">
                                        <SlugField
                                            value={form.data.slug}
                                            onChange={(slug) =>
                                                form.setData('slug', slug)
                                            }
                                            name={form.data.name}
                                            current={car?.slug}
                                            prefix="/fleet/"
                                            error={form.errors.slug}
                                            disabled={!editable}
                                        />
                                    </div>
                                    <SelectField
                                        name="brand_id"
                                        label="Brand"
                                        required
                                        placeholder="Choose a brand"
                                        options={brandOptions}
                                        disabled={!editable}
                                        value={form.data.brand_id}
                                        error={form.errors.brand_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'brand_id',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <SelectField
                                        name="car_type_id"
                                        label="Car type"
                                        required
                                        placeholder="Choose a type"
                                        options={typeOptions}
                                        disabled={!editable}
                                        value={form.data.car_type_id}
                                        error={form.errors.car_type_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'car_type_id',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextField
                                        name="engine_type"
                                        label="Engine"
                                        placeholder="4.5L V8 Turbo Diesel"
                                        disabled={!editable}
                                        value={form.data.engine_type}
                                        error={form.errors.engine_type}
                                        onChange={(event) =>
                                            form.setData(
                                                'engine_type',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextField
                                        name="engine_capacity_litres"
                                        label="Engine capacity (L)"
                                        type="number"
                                        inputMode="decimal"
                                        step={0.1}
                                        min={0.6}
                                        max={10}
                                        placeholder="2.5"
                                        disabled={!editable}
                                        value={form.data.engine_capacity_litres}
                                        error={
                                            form.errors.engine_capacity_litres
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'engine_capacity_litres',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <SelectField
                                        name="fuel_type"
                                        label="Fuel"
                                        required
                                        options={fuelOptions}
                                        disabled={!editable}
                                        value={form.data.fuel_type}
                                        error={form.errors.fuel_type}
                                        onChange={(event) =>
                                            form.setData(
                                                'fuel_type',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <SelectField
                                        name="transmission"
                                        label="Transmission"
                                        required
                                        options={transmissionOptions}
                                        disabled={!editable}
                                        value={form.data.transmission}
                                        error={form.errors.transmission}
                                        onChange={(event) =>
                                            form.setData(
                                                'transmission',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextField
                                        name="doors"
                                        label="Doors"
                                        type="number"
                                        required
                                        min={1}
                                        max={8}
                                        disabled={!editable}
                                        value={form.data.doors}
                                        error={form.errors.doors}
                                        onChange={(event) =>
                                            form.setData(
                                                'doors',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextField
                                        name="seats"
                                        label="Seats"
                                        type="number"
                                        required
                                        min={1}
                                        max={60}
                                        disabled={!editable}
                                        value={form.data.seats}
                                        error={form.errors.seats}
                                        onChange={(event) =>
                                            form.setData(
                                                'seats',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextField
                                        name="colour"
                                        label="Colour"
                                        placeholder="Pearl white"
                                        disabled={!editable}
                                        value={form.data.colour}
                                        error={form.errors.colour}
                                        onChange={(event) =>
                                            form.setData(
                                                'colour',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextField
                                        name="model_year"
                                        label="Model year"
                                        type="number"
                                        min={1950}
                                        disabled={!editable}
                                        value={form.data.model_year}
                                        error={form.errors.model_year}
                                        onChange={(event) =>
                                            form.setData(
                                                'model_year',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextareaField
                                        name="description"
                                        label="Description"
                                        rows={5}
                                        className="sm:col-span-2"
                                        disabled={!editable}
                                        value={form.data.description}
                                        error={form.errors.description}
                                        onChange={(event) =>
                                            form.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="pricing" className="mt-4">
                            <Card>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <MoneyField
                                        name="daily_rate"
                                        label="Rate per day"
                                        required
                                        placeholder="15000"
                                        disabled={!editable}
                                        value={form.data.daily_rate}
                                        error={form.errors.daily_rate}
                                        onChange={(event) =>
                                            form.setData(
                                                'daily_rate',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextField
                                        name="mileage_limit_km"
                                        label="Mileage limit (km per day)"
                                        type="number"
                                        min={1}
                                        placeholder={`${defaultMileageKm} (site default)`}
                                        description="Leave empty to use the site default."
                                        disabled={!editable}
                                        value={form.data.mileage_limit_km}
                                        error={form.errors.mileage_limit_km}
                                        onChange={(event) =>
                                            form.setData(
                                                'mileage_limit_km',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Field
                                        label="Damage excess"
                                        error={
                                            form.errors.damage_excess ??
                                            form.errors.damage_excess_currency
                                        }
                                        description="What the renter pays towards damage. Leave empty to hide it."
                                    >
                                        <div className="flex gap-2">
                                            <NativeSelect
                                                aria-label="Damage excess currency"
                                                className="w-24"
                                                disabled={!editable}
                                                value={
                                                    form.data
                                                        .damage_excess_currency
                                                }
                                                options={currencies.map(
                                                    (code) => ({
                                                        value: code,
                                                        label: code,
                                                    }),
                                                )}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'damage_excess_currency',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <input
                                                aria-label="Damage excess amount"
                                                inputMode="decimal"
                                                pattern="^\d+(\.\d{1,2})?$"
                                                placeholder="100000"
                                                disabled={!editable}
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm tabular-nums shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 dark:bg-input/30"
                                                value={form.data.damage_excess}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'damage_excess',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </Field>
                                    {canSeeValue && (
                                        <MoneyField
                                            name="car_value"
                                            label="Car value"
                                            placeholder="4500000"
                                            description="Admin only. Never shown on the site."
                                            disabled={!editable}
                                            value={form.data.car_value}
                                            error={form.errors.car_value}
                                            onChange={(event) =>
                                                form.setData(
                                                    'car_value',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="photos" className="mt-4">
                            <Card>
                                <CardContent className="grid gap-2">
                                    <p className="text-sm text-muted-foreground">
                                        Up to {galleryLimits.max_files} photos.
                                        Drag to reorder and star the cover shown
                                        on fleet cards.
                                    </p>
                                    <MediaUploader
                                        limits={galleryLimits}
                                        value={gallery}
                                        sortable
                                        coverable
                                        cover={form.data.cover}
                                        disabled={!editable}
                                        error={
                                            form.errors.gallery ??
                                            form.errors.cover ??
                                            errors['gallery.0']
                                        }
                                        onCoverChange={(uuid) =>
                                            form.setData('cover', uuid)
                                        }
                                        onChange={(items) => {
                                            setGallery(items);
                                            form.setData(
                                                'gallery',
                                                items.map((item) => item.uuid),
                                            );
                                        }}
                                    />
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="extra" className="mt-4">
                            <Card>
                                <CardContent>
                                    <KeyValueField
                                        name="metadata"
                                        label="Extra info"
                                        description="Anything else worth recording, such as 0–100 time, top speed, drive or features. Kept for staff; not shown on the site yet."
                                        value={form.data.metadata_rows}
                                        errors={errors}
                                        disabled={!editable}
                                        onChange={(rows) =>
                                            form.setData('metadata_rows', rows)
                                        }
                                    />
                                </CardContent>
                            </Card>
                        </TabsContent>

                        {car && (
                            <TabsContent value="blackouts" className="mt-4">
                                <Card>
                                    <CardContent>
                                        <CarBlackouts
                                            carSlug={car.slug}
                                            blackouts={car.blackouts ?? []}
                                            reasons={blackoutReasons}
                                            editable={can(
                                                'availability.manage',
                                            )}
                                        />
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        )}
                    </Tabs>

                    {editable && tab !== 'blackouts' && (
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    (car !== null && !form.isDirty)
                                }
                            >
                                {car ? 'Save car' : 'Create car'}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={CarController.index()}>Cancel</Link>
                            </Button>
                        </div>
                    )}
                </form>
            </AdminPage>
        </>
    );
}

function CarActions({ car, dirty }: { car: AdminCar; dirty: boolean }) {
    const can = useCan();

    return (
        <>
            {can('cars.publish') &&
                (car.is_published ? (
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.delete(
                                CarController.unpublish.url(car.slug),
                                { preserveScroll: true },
                            )
                        }
                    >
                        <EyeOff /> Unpublish
                    </Button>
                ) : (
                    <Button
                        disabled={dirty}
                        title={dirty ? 'Save your changes first' : undefined}
                        onClick={() =>
                            router.post(
                                CarController.publish.url(car.slug),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Eye /> Publish
                    </Button>
                ))}
            {can('cars.delete') && (
                <ConfirmDialog
                    trigger={<Button variant="destructive">Delete</Button>}
                    title={`Delete ${car.name}?`}
                    description="It disappears from the site and the admin. Past bookings keep their record of it."
                    confirmLabel="Delete car"
                    destructive
                    onConfirm={() =>
                        router.delete(CarController.destroy.url(car.slug))
                    }
                />
            )}
        </>
    );
}

CarForm.layout = {
    breadcrumbs: [{ title: 'Cars', href: CarController.index() }],
};
