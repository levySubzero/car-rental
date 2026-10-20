import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import CarController from '@/actions/App/Http/Controllers/Admin/CarController';
import { CarCard } from '@/components/admin/catalog/car-card';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { MediaThumb } from '@/components/admin/catalog/media-thumb';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import type { AdminCar, Option, Paginated } from '@/types';

type Filters = {
    search?: string;
    brand?: string;
    type?: string;
    status?: string;
    available?: string;
    featured?: string;
    sort?: string;
    direction?: string;
};

export default function CarsIndex({
    cars,
    filters,
    brandOptions,
    typeOptions,
}: {
    cars: Paginated<AdminCar>;
    filters: Filters;
    brandOptions: Option[];
    typeOptions: Option[];
}) {
    const can = useCan();
    const apply = useTableQuery(filters);
    const canUpdate = can('cars.update');

    const columns: Column<AdminCar>[] = [
        {
            key: 'name',
            header: 'Car',
            sortKey: 'name',
            cell: (car) => (
                <div className="flex items-center gap-3">
                    <MediaThumb media={car.cover} className="h-10 w-14" />
                    <div className="grid">
                        <Link
                            href={CarController.edit(car.slug)}
                            className="font-medium hover:underline"
                        >
                            {car.name}
                        </Link>
                        <span className="text-xs text-muted-foreground">
                            {[car.brand?.name, car.car_type?.name]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'daily_rate',
            header: 'Per day',
            sortKey: 'daily_rate_minor',
            className: 'tabular-nums',
            cell: (car) => car.daily_rate.display,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (car) =>
                car.is_published ? (
                    <Badge>Published</Badge>
                ) : (
                    <Badge variant="outline">Draft</Badge>
                ),
        },
        {
            key: 'available',
            header: 'Available',
            cell: (car) => (
                <FlagSwitch
                    key={`${car.id}-${car.is_available}`}
                    url={CarController.availability.url(car.slug)}
                    checked={car.is_available}
                    label={`${car.name} available to book`}
                    disabled={!canUpdate}
                />
            ),
        },
        {
            key: 'featured',
            header: 'Featured',
            className: 'hidden md:table-cell',
            cell: (car) => (
                <FlagSwitch
                    key={`${car.id}-${car.is_featured}`}
                    url={CarController.featured.url(car.slug)}
                    checked={car.is_featured}
                    label={`Feature ${car.name} on the home page`}
                    disabled={!canUpdate}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Cars" />
            <AdminPage>
                <PageHeader
                    title="Cars"
                    description="The fleet. Drafts are only visible here until you publish them."
                    actions={
                        can('cars.create') && (
                            <Button asChild>
                                <Link href={CarController.create()}>
                                    <Plus /> New car
                                </Link>
                            </Button>
                        )
                    }
                />
                <DataTable
                    data={cars}
                    columns={columns}
                    filters={filters}
                    rowKey={(car) => car.id}
                    viewKey="cars"
                    grid={(car) => <CarCard car={car} canUpdate={canUpdate} />}
                    searchPlaceholder="Search cars"
                    empty="No cars match."
                    toolbar={
                        <>
                            <NativeSelect
                                aria-label="Brand"
                                className="sm:w-40"
                                value={filters.brand ?? ''}
                                placeholder="All brands"
                                options={brandOptions}
                                onChange={(event) =>
                                    apply({ brand: event.target.value })
                                }
                            />
                            <NativeSelect
                                aria-label="Car type"
                                className="sm:w-40"
                                value={filters.type ?? ''}
                                placeholder="All types"
                                options={typeOptions}
                                onChange={(event) =>
                                    apply({ type: event.target.value })
                                }
                            />
                            <NativeSelect
                                aria-label="Status"
                                className="sm:w-36"
                                value={filters.status ?? ''}
                                placeholder="Any status"
                                options={[
                                    { value: 'published', label: 'Published' },
                                    { value: 'draft', label: 'Draft' },
                                ]}
                                onChange={(event) =>
                                    apply({ status: event.target.value })
                                }
                            />
                            <NativeSelect
                                aria-label="Availability"
                                className="sm:w-40"
                                value={filters.available ?? ''}
                                placeholder="Any availability"
                                options={[
                                    { value: 'yes', label: 'Available' },
                                    { value: 'no', label: 'Unavailable' },
                                ]}
                                onChange={(event) =>
                                    apply({ available: event.target.value })
                                }
                            />
                        </>
                    }
                />
            </AdminPage>
        </>
    );
}

CarsIndex.layout = {
    breadcrumbs: [{ title: 'Cars', href: CarController.index() }],
};
