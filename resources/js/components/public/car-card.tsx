import { Link } from '@inertiajs/react';
import { Cog, Fuel, Gauge, Users } from 'lucide-react';
import { BrandIcon } from '@/components/public/icons';
import { MediaImage } from '@/components/media/media-image';
import { Button } from '@/components/ui/button';
import { useSite } from '@/hooks/use-site';
import { displayPrice } from '@/lib/money';
import { cn } from '@/lib/utils';
import { show } from '@/routes/cars';
import type { CarCard as CarCardData } from '@/types';

/**
 * A fleet card (M7.8, PRD F-2): photo, brand, name, price per day in the renter's
 * currency, key specs, and View, Book and WhatsApp actions.
 */
export function CarCard({
    car,
    className,
    priority = false,
}: {
    car: CarCardData;
    className?: string;
    priority?: boolean;
}) {
    const { currency, whatsapp } = useSite();
    const href = show(car.slug).url;
    const chat = whatsapp(car.name);

    const specs = [
        { icon: Cog, label: car.transmission },
        { icon: Users, label: `${car.seats} seats` },
        { icon: Fuel, label: car.fuel_type },
        { icon: Gauge, label: `${car.mileage_limit_km} km/day` },
    ];

    return (
        <article
            className={cn(
                'group relative flex flex-col overflow-hidden rounded-md border border-white/8 bg-surface transition duration-300 hover:border-gold/40 hover:shadow-2xl hover:shadow-black/50 motion-safe:hover:-translate-y-1',
                className,
            )}
        >
            <div className="relative aspect-4/3 overflow-hidden bg-surface-raised">
                {car.cover && (
                    <MediaImage
                        media={car.cover}
                        conversion="card"
                        sizes="(min-width: 1280px) 33vw, (min-width: 640px) 50vw, 85vw"
                        loading={priority ? 'eager' : 'lazy'}
                        alt=""
                        className="size-full object-cover transition-transform duration-700 motion-safe:group-hover:scale-105"
                    />
                )}
                <div className="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-surface to-transparent" />
                {car.brand && (
                    <span className="absolute top-3 left-3 rounded bg-ink/80 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
                        {car.brand.logo?.thumb ? (
                            <img
                                src={car.brand.logo.thumb}
                                alt={car.brand.name}
                                className="h-4 w-auto"
                                loading="lazy"
                            />
                        ) : (
                            car.brand.name
                        )}
                    </span>
                )}
                {!car.is_available && (
                    <span className="absolute top-3 right-3 rounded bg-white/90 px-2 py-1 text-xs font-semibold text-ink">
                        Currently booked
                    </span>
                )}
            </div>

            <div className="flex flex-1 flex-col p-5">
                {car.car_type && (
                    <p className="text-sm text-white/55">{car.car_type.name}</p>
                )}
                <h3 className="mt-1 font-display text-xl text-white">
                    <Link
                        href={href}
                        className="group-focus-within:text-gold-light after:absolute after:inset-0 focus-visible:outline-none"
                    >
                        {car.name}
                    </Link>
                </h3>
                <p className="mt-2 text-gold">
                    <span className="text-lg font-semibold tabular-nums">
                        {displayPrice(car.daily_rate.minor, currency)}
                    </span>
                    <span className="text-sm text-white/60"> / day</span>
                </p>

                <ul className="mt-4 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-white/8 pt-4 text-xs text-white/70">
                    {specs.map(({ icon: Icon, label }) => (
                        <li key={label} className="flex items-center gap-1.5">
                            <Icon
                                className="size-3.5 text-gold/80"
                                aria-hidden
                            />
                            {label}
                        </li>
                    ))}
                </ul>

                {/* Above the stretched name link so the buttons stay clickable. */}
                <div className="relative z-10 mt-5 flex gap-2 pt-1">
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="flex-1 border-white/15 bg-transparent text-white hover:border-gold hover:bg-transparent hover:text-gold"
                    >
                        <Link href={href}>View</Link>
                    </Button>
                    <Button asChild size="sm" className="flex-1 font-semibold">
                        <Link href={`${href}#book`}>Book</Link>
                    </Button>
                    {chat && (
                        <Button
                            asChild
                            size="sm"
                            className="bg-[#25D366] px-3 text-ink hover:bg-[#25D366]/90"
                            aria-label={`Ask about the ${car.name} on WhatsApp`}
                        >
                            <a
                                href={chat}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <BrandIcon name="whatsapp" className="size-4" />
                            </a>
                        </Button>
                    )}
                </div>
            </div>
        </article>
    );
}
