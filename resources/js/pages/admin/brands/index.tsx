import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import { BrandCard } from '@/components/admin/catalog/brand-card';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { MediaThumb } from '@/components/admin/catalog/media-thumb';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCan } from '@/hooks/use-can';
import type { AdminBrand, Paginated } from '@/types';

type Filters = {
    search?: string;
    status?: string;
    sort?: string;
    direction?: string;
};

export default function BrandsIndex({
    brands,
    filters,
    marquee,
}: {
    brands: Paginated<AdminBrand>;
    filters: Filters;
    marquee: AdminBrand[];
}) {
    const can = useCan();
    const apply = useTableQuery(filters);

    const columns: Column<AdminBrand>[] = [
        {
            key: 'name',
            header: 'Brand',
            sortKey: 'name',
            cell: (brand) => (
                <div className="flex items-center gap-3">
                    <MediaThumb media={brand.logo} fit="contain" />
                    <div className="grid">
                        <Link
                            href={BrandController.edit(brand.slug)}
                            className="font-medium hover:underline"
                        >
                            {brand.name}
                        </Link>
                        <span className="text-xs text-muted-foreground">
                            /{brand.slug}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'cars_count',
            header: 'Cars',
            sortKey: 'cars_count',
            className: 'hidden sm:table-cell',
            cell: (brand) => brand.cars_count ?? 0,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (brand) =>
                brand.is_active ? (
                    <Badge variant="secondary">Active</Badge>
                ) : (
                    <Badge variant="outline">Hidden</Badge>
                ),
        },
        {
            key: 'marquee',
            header: 'In marquee',
            cell: (brand) => (
                <FlagSwitch
                    key={`${brand.id}-${brand.show_in_marquee}`}
                    url={BrandController.marquee.url(brand.slug)}
                    checked={brand.show_in_marquee}
                    label={`Show ${brand.name} in the marquee`}
                    disabled={!can('brands.update')}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Brands" />
            <AdminPage>
                <PageHeader
                    title="Brands"
                    description="Car makes, their logos and the home page marquee."
                    actions={
                        can('brands.create') && (
                            <Button asChild>
                                <Link href={BrandController.create()}>
                                    <Plus /> New brand
                                </Link>
                            </Button>
                        )
                    }
                />
                <Tabs defaultValue="all">
                    <TabsList>
                        <TabsTrigger value="all">All brands</TabsTrigger>
                        <TabsTrigger value="marquee">Marquee order</TabsTrigger>
                    </TabsList>
                    <TabsContent value="all" className="mt-4">
                        <DataTable
                            data={brands}
                            columns={columns}
                            filters={filters}
                            rowKey={(brand) => brand.id}
                            viewKey="brands"
                            grid={(brand) => (
                                <BrandCard
                                    brand={brand}
                                    canUpdate={can('brands.update')}
                                />
                            )}
                            searchPlaceholder="Search brands"
                            empty="No brands yet."
                            toolbar={
                                <NativeSelect
                                    aria-label="Status"
                                    className="sm:w-40"
                                    value={filters.status ?? ''}
                                    placeholder="Any status"
                                    options={[
                                        { value: 'active', label: 'Active' },
                                        { value: 'inactive', label: 'Hidden' },
                                    ]}
                                    onChange={(event) =>
                                        apply({ status: event.target.value })
                                    }
                                />
                            }
                        />
                    </TabsContent>
                    <TabsContent value="marquee" className="mt-4 max-w-2xl">
                        <MarqueeOrder
                            brands={marquee}
                            editable={can('brands.update')}
                        />
                    </TabsContent>
                </Tabs>
            </AdminPage>
        </>
    );
}

function MarqueeOrder({
    brands,
    editable,
}: {
    brands: AdminBrand[];
    editable: boolean;
}) {
    const [items, setItems] = useState(brands);

    if (items.length === 0) {
        return <p className="text-sm text-muted-foreground">No brands yet.</p>;
    }

    const row = (brand: AdminBrand) => (
        <div className="flex items-center gap-3">
            <MediaThumb media={brand.logo} fit="contain" className="size-8" />
            <span className="min-w-0 flex-1 truncate text-sm font-medium">
                {brand.name}
            </span>
            {!brand.is_active && <Badge variant="outline">Hidden</Badge>}
            {!brand.show_in_marquee && (
                <Badge variant="outline">Not in marquee</Badge>
            )}
        </div>
    );

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                Drag to set the order logos scroll in on the home page. Hidden
                brands and brands not in the marquee keep their place but are
                not shown.
            </p>
            {editable ? (
                <SortableList
                    items={items}
                    renderItem={row}
                    onReorder={(ids, reordered) => {
                        setItems(reordered);
                        router.put(
                            BrandController.reorder.url(),
                            { ids },
                            { preserveScroll: true, preserveState: true },
                        );
                    }}
                />
            ) : (
                <ul className="space-y-2">
                    {items.map((brand) => (
                        <li key={brand.id} className="rounded-md border p-2">
                            {row(brand)}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

BrandsIndex.layout = {
    breadcrumbs: [{ title: 'Brands', href: BrandController.index() }],
};
