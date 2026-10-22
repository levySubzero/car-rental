import { Link } from '@inertiajs/react';
import { CarCard } from '@/components/public/car-card';
import { SectionHeading } from '@/components/public/section-heading';
import { cn } from '@/lib/utils';
import { fleet } from '@/routes';
import { show as carTypeShow } from '@/routes/car-types';
import type { CarCard as CarCardData } from '@/types';
import type { HomeProps } from './types';

function ViewAll({ href, children }: { href: string; children: string }) {
    return (
        <Link
            href={href}
            className="text-sm font-semibold text-accent-gold underline-offset-4 hover:underline"
        >
            {children}
        </Link>
    );
}

/**
 * Cards in a grid on large screens and a swipeable row on phones.
 */
function CarRow({
    cars,
    priority = false,
    wide = false,
}: {
    cars: CarCardData[];
    priority?: boolean;
    // Three larger cards per row instead of four.
    wide?: boolean;
}) {
    return (
        <ul
            className={cn(
                '-mx-4 mt-10 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0',
                wide ? 'lg:grid-cols-3' : 'xl:grid-cols-4',
            )}
        >
            {cars.map((car, index) => (
                <li
                    key={car.slug}
                    className="w-[82%] shrink-0 snap-start sm:w-auto"
                >
                    <CarCard
                        car={car}
                        priority={priority && index < 2}
                        className="h-full"
                    />
                </li>
            ))}
        </ul>
    );
}

export function FeaturedFleet({ cars }: { cars: HomeProps['featured'] }) {
    if (cars.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="featured-heading"
            className="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8"
        >
            <div className="flex flex-wrap items-end justify-between gap-6">
                <SectionHeading id="featured-heading" title="Featured cars" />
                <ViewAll href={fleet().url}>View the full fleet</ViewAll>
            </div>
            <CarRow cars={cars} priority wide />
        </section>
    );
}

export function CarTypeRows({ rows }: { rows: HomeProps['car_type_rows'] }) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <div>
            {rows.map(({ type, cars }, index) => (
                <section
                    key={type.slug}
                    aria-labelledby={`type-${type.slug}`}
                    // Rows alternate charcoal and black so each type reads as its own band.
                    className={index % 2 === 0 ? 'tone-charcoal' : 'tone-ink'}
                >
                    <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                        <div className="flex flex-wrap items-end justify-between gap-6">
                            <SectionHeading
                                id={`type-${type.slug}`}
                                title={type.name}
                            >
                                {type.description && <p>{type.description}</p>}
                            </SectionHeading>
                            <ViewAll
                                href={carTypeShow(type.slug).url}
                            >{`View all ${type.name}`}</ViewAll>
                        </div>
                        <CarRow cars={cars} />
                    </div>
                </section>
            ))}
        </div>
    );
}
