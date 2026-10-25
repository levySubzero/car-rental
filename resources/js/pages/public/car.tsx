import { Link } from '@inertiajs/react';
import { Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import { CarCard } from '@/components/public/car-card';
import { CarGallery } from '@/components/public/car-gallery';
import { BrandIcon } from '@/components/public/icons';
import { Seo } from '@/components/public/seo';
import { Button } from '@/components/ui/button';
import { useSite } from '@/hooks/use-site';
import { displayPrice } from '@/lib/money';
import { carJsonLd } from '@/lib/structured-data';
import { fleet, rentalPolicy } from '@/routes';
import { create as book } from '@/routes/bookings';
import { show as carTypeShow } from '@/routes/car-types';
import type { CarCard as CarCardData, CarDetail } from '@/types';

/**
 * A car's page (M7.11, PRD F-3–F-5): photos, the information block, and the actions.
 */
export default function Car({
    car,
    related,
    reservation_percent: reservation,
}: {
    car: CarDetail;
    related: CarCardData[];
    reservation_percent: number;
}) {
    const { site, currency, whatsapp, telHref, phone } = useSite();
    const absolute = (src?: string) =>
        src ? (src.startsWith('http') ? src : `${site.url}${src}`) : null;
    const chat = whatsapp(car.name);
    const photos =
        car.gallery.length > 0 ? car.gallery : car.cover ? [car.cover] : [];
    const price = displayPrice(car.daily_rate.minor, currency);

    const engine = [
        car.engine_type,
        car.engine_capacity_litres && `${car.engine_capacity_litres} L`,
    ]
        .filter(Boolean)
        .join(', ');

    const specs: [string, ReactNode][] = [
        ['Engine', engine || null],
        ['Fuel', car.fuel_type],
        ['Gearbox', car.transmission],
        ['Doors', car.doors],
        ['Seats', car.seats],
        ['Colour', car.colour],
        ['Year', car.model_year],
        ['Mileage', `${car.mileage_limit_km} km per day`],
        ['Damage excess', car.damage_excess?.display ?? null],
    ];

    const title = car.brand ? `${car.name} rental in Nairobi` : car.name;

    return (
        <>
            <Seo
                title={title}
                description={`Rent the ${car.name} in Nairobi from ${car.daily_rate.display} per day. ${car.seats} seats, ${car.transmission.toLowerCase()}, ${car.mileage_limit_km} km per day included.`}
                image={absolute(
                    photos[0]?.urls.full ?? photos[0]?.urls.original,
                )}
                jsonLd={carJsonLd(car, site, `${site.url}/cars/${car.slug}`)}
            />

            <div className="mx-auto max-w-7xl px-4 pt-8 pb-20 sm:px-6 lg:px-8">
                <nav
                    aria-label="Breadcrumb"
                    className="text-sm text-muted-foreground"
                >
                    <ol className="flex flex-wrap items-center gap-2">
                        <li>
                            <Link
                                href={fleet()}
                                className="hover:text-foreground"
                            >
                                Fleet
                            </Link>
                        </li>
                        {car.car_type && (
                            <>
                                <li aria-hidden>/</li>
                                <li>
                                    <Link
                                        href={carTypeShow(car.car_type.slug)}
                                        className="hover:text-foreground"
                                    >
                                        {car.car_type.name}
                                    </Link>
                                </li>
                            </>
                        )}
                        <li aria-hidden>/</li>
                        <li aria-current="page" className="text-foreground">
                            {car.name}
                        </li>
                    </ol>
                </nav>

                <div className="mt-6 grid gap-10 lg:grid-cols-12 lg:gap-12">
                    <div className="min-w-0 lg:col-span-7">
                        <CarGallery photos={photos} name={car.name} />
                    </div>

                    <section
                        aria-labelledby="car-name"
                        className="min-w-0 lg:col-span-5"
                    >
                        <div className="lg:sticky lg:top-24">
                            {car.brand && (
                                <p className="text-sm text-muted-foreground">
                                    {car.brand.name}
                                </p>
                            )}
                            <h1
                                id="car-name"
                                className="mt-1 font-display text-4xl leading-tight text-foreground"
                            >
                                {car.name}
                            </h1>
                            <p className="mt-4 text-gold">
                                <span className="text-3xl font-semibold tabular-nums">
                                    {price}
                                </span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    per day
                                </span>
                            </p>
                            {!car.is_available && (
                                <p className="mt-3 inline-block rounded bg-white/90 px-2.5 py-1 text-sm font-semibold text-ink">
                                    Currently booked: ask us for the next free
                                    dates
                                </p>
                            )}

                            <dl className="mt-8 grid grid-cols-2 gap-x-6 border-t border-white/10">
                                {specs
                                    .filter(
                                        ([, value]) =>
                                            value !== null && value !== '',
                                    )
                                    .map(([label, value]) => (
                                        <div
                                            key={label}
                                            className="border-b border-white/10 py-3"
                                        >
                                            <dt className="text-xs text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="mt-0.5 font-medium text-foreground tabular-nums">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                            </dl>

                            <div className="mt-8 grid gap-3">
                                <Button
                                    asChild
                                    size="lg"
                                    className="h-12 text-base font-semibold"
                                >
                                    <Link href={book(car.slug)}>
                                        Book this car
                                    </Link>
                                </Button>
                                <div className="grid grid-cols-2 gap-3">
                                    {chat && (
                                        <Button
                                            asChild
                                            size="lg"
                                            className="h-12 bg-[#25D366] font-semibold text-ink hover:bg-[#25D366]/90"
                                        >
                                            <a
                                                href={chat}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <BrandIcon
                                                    name="whatsapp"
                                                    className="size-4"
                                                />
                                                WhatsApp
                                            </a>
                                        </Button>
                                    )}
                                    {telHref && (
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                            className="h-12 border-white/20 bg-transparent text-white hover:border-gold hover:bg-white/5 hover:text-white"
                                        >
                                            <a
                                                href={telHref}
                                                aria-label={`Call ${phone}`}
                                            >
                                                <Phone className="size-4" />
                                                Call
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <p className="mt-5 text-sm leading-relaxed text-muted-foreground">
                                Pay {reservation}% to reserve and the balance at
                                handover, with a refundable security deposit.{' '}
                                <Link
                                    href={rentalPolicy()}
                                    className="text-gold underline underline-offset-4"
                                >
                                    Rental policy
                                </Link>
                            </p>
                        </div>
                    </section>
                </div>

                {car.description && (
                    <section
                        aria-labelledby="about-car"
                        className="mt-16 max-w-3xl"
                    >
                        <h2
                            id="about-car"
                            className="font-display text-2xl text-foreground"
                        >
                            About this car
                        </h2>
                        <div className="mt-4 space-y-4 leading-relaxed text-muted-foreground">
                            {car.description
                                .split(/\n{2,}/)
                                .map((paragraph, i) => (
                                    <p key={i}>{paragraph}</p>
                                ))}
                        </div>
                    </section>
                )}

                {related.length > 0 && (
                    <section aria-labelledby="related" className="mt-20">
                        <h2
                            id="related"
                            className="font-display text-3xl text-foreground"
                        >
                            More cars to consider
                        </h2>
                        <ul className="mt-8 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                            {related.map((other) => (
                                <li key={other.slug}>
                                    <CarCard car={other} className="h-full" />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}
