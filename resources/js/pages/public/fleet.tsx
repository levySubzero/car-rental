import { Link, router } from '@inertiajs/react';
import { useId } from 'react';
import { CarCard } from '@/components/public/car-card';
import { PageHeader } from '@/components/public/page-header';
import { Seo } from '@/components/public/seo';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { fleet } from '@/routes';
import type { CarCard as CarCardData, PageImage, Paginated } from '@/types';

// Mirrors App\Http\Controllers\Public\FleetController.
type Filters = {
    brand: string | null;
    type: string | null;
    transmission: string | null;
    seats: number | null;
    sort: 'recommended' | 'price_asc' | 'price_desc';
};

type FleetProps = {
    heading: string;
    intro: string | null;
    preset: { kind: 'brand' | 'type'; slug: string } | null;
    header_image: PageImage | null;
    filters: Filters;
    options: {
        brands: { name: string; slug: string }[];
        types: { name: string; slug: string }[];
        transmissions: { value: string; label: string }[];
        seats: number[];
    };
    cars: Paginated<CarCardData>;
};

const SORTS: { value: Filters['sort']; label: string }[] = [
    { value: 'recommended', label: 'Recommended' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
];

/**
 * The fleet (M7.9) and the brand and car type pages (M7.10). Filters live in the query
 * string, so every filtered view can be bookmarked and shared.
 */
export default function Fleet({
    heading,
    intro,
    preset,
    header_image: headerImage,
    filters,
    options,
    cars,
}: FleetProps) {
    // The preset brand or type is in the path; never repeat it in the query.
    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const query = Object.fromEntries(
            Object.entries(merged).filter(
                ([key, value]) =>
                    value !== null &&
                    !(key === 'sort' && value === 'recommended') &&
                    key !== preset?.kind,
            ),
        );

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['cars', 'filters'],
        });
    };

    const active =
        (preset?.kind !== 'brand' && filters.brand !== null) ||
        (preset?.kind !== 'type' && filters.type !== null) ||
        filters.transmission !== null ||
        filters.seats !== null;

    const clear = () =>
        apply({
            brand: null,
            type: null,
            transmission: null,
            seats: null,
            sort: 'recommended',
        });

    const total = cars.meta.total;

    return (
        <>
            <Seo
                title={heading}
                description={
                    intro ??
                    `Rent ${heading === 'Our fleet' ? 'executive cars, SUVs and safari Land Cruisers' : heading} in Nairobi, self-drive or with a chauffeur.`
                }
            />

            <PageHeader
                title={heading}
                intro={intro}
                image={headerImage}
                crumbs={preset ? [{ label: 'Fleet', href: fleet().url }] : []}
            />

            <section
                aria-label="Cars"
                className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8"
            >
                <form
                    role="search"
                    aria-label="Filter the fleet"
                    onSubmit={(event) => event.preventDefault()}
                    className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5"
                >
                    {preset?.kind !== 'brand' && (
                        <FilterSelect
                            label="Brand"
                            value={filters.brand ?? ''}
                            onChange={(value) =>
                                apply({ brand: value || null })
                            }
                            options={[
                                { value: '', label: 'All brands' },
                                ...options.brands.map((brand) => ({
                                    value: brand.slug,
                                    label: brand.name,
                                })),
                            ]}
                        />
                    )}
                    {preset?.kind !== 'type' && (
                        <FilterSelect
                            label="Car type"
                            value={filters.type ?? ''}
                            onChange={(value) => apply({ type: value || null })}
                            options={[
                                { value: '', label: 'All types' },
                                ...options.types.map((type) => ({
                                    value: type.slug,
                                    label: type.name,
                                })),
                            ]}
                        />
                    )}
                    <FilterSelect
                        label="Gearbox"
                        value={filters.transmission ?? ''}
                        onChange={(value) =>
                            apply({ transmission: value || null })
                        }
                        options={[
                            { value: '', label: 'Any gearbox' },
                            ...options.transmissions,
                        ]}
                    />
                    <FilterSelect
                        label="Seats"
                        value={
                            filters.seats === null ? '' : String(filters.seats)
                        }
                        onChange={(value) =>
                            apply({ seats: value ? Number(value) : null })
                        }
                        options={[
                            { value: '', label: 'Any number' },
                            ...options.seats.map((seats) => ({
                                value: String(seats),
                                label: `${seats} or more`,
                            })),
                        ]}
                    />
                    <FilterSelect
                        label="Sort by"
                        value={filters.sort}
                        onChange={(value) =>
                            apply({ sort: value as Filters['sort'] })
                        }
                        options={SORTS}
                    />
                </form>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <p aria-live="polite">
                        {total === 1 ? '1 car' : `${total} cars`}
                    </p>
                    {active && (
                        <button
                            type="button"
                            onClick={clear}
                            className="font-semibold text-gold underline-offset-4 hover:underline"
                        >
                            Clear filters
                        </button>
                    )}
                </div>

                {cars.data.length === 0 ? (
                    <div className="mt-10 rounded-lg border border-white/10 px-6 py-16 text-center">
                        <h2 className="text-xl font-semibold text-foreground">
                            No cars match these filters
                        </h2>
                        <p className="mt-2 text-muted-foreground">
                            Try another gearbox or fewer seats, or ask us on
                            WhatsApp and we will find you a car.
                        </p>
                        {active && (
                            <Button
                                onClick={clear}
                                className="mt-6 font-semibold"
                            >
                                Clear filters
                            </Button>
                        )}
                    </div>
                ) : (
                    <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {cars.data.map((car, index) => (
                            <li key={car.slug}>
                                <CarCard
                                    car={car}
                                    priority={index < 3}
                                    className="h-full"
                                />
                            </li>
                        ))}
                    </ul>
                )}

                {cars.meta.last_page > 1 && <Pagination cars={cars} />}
            </section>
        </>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
}) {
    const id = useId();

    return (
        <div className="grid min-w-0 gap-1.5">
            <label
                htmlFor={id}
                className="text-xs font-medium text-muted-foreground"
            >
                {label}
            </label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-11 w-full min-w-0 rounded-md border border-white/15 bg-surface px-3 text-sm text-foreground focus-visible:border-gold focus-visible:ring-2 focus-visible:ring-gold/40 focus-visible:outline-none"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function Pagination({ cars }: { cars: Paginated<CarCardData> }) {
    const { current_page: page, last_page: last } = cars.meta;
    const linkClass =
        'inline-flex h-11 items-center rounded-md border border-white/15 px-5 text-sm font-semibold text-foreground transition-colors hover:border-gold';

    return (
        <nav
            aria-label="Pages"
            className="mt-12 flex items-center justify-between gap-4"
        >
            {cars.links.prev ? (
                <Link
                    href={cars.links.prev}
                    preserveScroll={false}
                    className={linkClass}
                    rel="prev"
                >
                    Previous
                </Link>
            ) : (
                <span />
            )}
            <p className="text-sm text-muted-foreground">
                Page {page} of {last}
            </p>
            {cars.links.next ? (
                <Link
                    href={cars.links.next}
                    className={cn(linkClass)}
                    rel="next"
                >
                    Next
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}
