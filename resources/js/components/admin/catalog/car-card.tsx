import { Link } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import CarController from '@/actions/App/Http/Controllers/Admin/CarController';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { Badge } from '@/components/ui/badge';
import type { AdminCar } from '@/types';

/**
 * A car in the admin grid view: cover, name, rate, status and the quick switches.
 */
export function CarCard({
    car,
    canUpdate,
}: {
    car: AdminCar;
    canUpdate: boolean;
}) {
    const cover = car.cover?.urls.card ?? car.cover?.urls.original;

    return (
        <article className="flex h-full flex-col overflow-hidden rounded-lg border bg-card">
            <Link
                href={CarController.edit(car.slug)}
                className="relative block aspect-[4/3] bg-muted"
            >
                {cover ? (
                    <img
                        src={cover}
                        alt={car.cover?.alt ?? car.name}
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <span className="flex h-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-6" />
                    </span>
                )}
                <span className="absolute top-2 left-2 flex gap-1">
                    {car.is_published ? (
                        <Badge>Published</Badge>
                    ) : (
                        <Badge variant="secondary">Draft</Badge>
                    )}
                    {car.is_featured && (
                        <Badge variant="secondary">Featured</Badge>
                    )}
                </span>
            </Link>
            <div className="flex flex-1 flex-col gap-3 p-3">
                <div className="grid min-w-0 gap-0.5">
                    <Link
                        href={CarController.edit(car.slug)}
                        className="truncate font-medium hover:underline"
                    >
                        {car.name}
                    </Link>
                    <span className="truncate text-xs text-muted-foreground">
                        {[car.brand?.name, car.car_type?.name]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </div>
                <p className="text-sm font-semibold tabular-nums">
                    {car.daily_rate.display}{' '}
                    <span className="font-normal text-muted-foreground">
                        / day
                    </span>
                </p>
                <div className="mt-auto grid grid-cols-2 gap-2 border-t pt-3 text-xs text-muted-foreground">
                    <label className="flex items-center justify-between gap-2">
                        Available
                        <FlagSwitch
                            key={`${car.id}-${car.is_available}`}
                            url={CarController.availability.url(car.slug)}
                            checked={car.is_available}
                            label={`${car.name} available to book`}
                            disabled={!canUpdate}
                        />
                    </label>
                    <label className="flex items-center justify-between gap-2">
                        Featured
                        <FlagSwitch
                            key={`${car.id}-${car.is_featured}`}
                            url={CarController.featured.url(car.slug)}
                            checked={car.is_featured}
                            label={`Feature ${car.name} on the home page`}
                            disabled={!canUpdate}
                        />
                    </label>
                </div>
            </div>
        </article>
    );
}
