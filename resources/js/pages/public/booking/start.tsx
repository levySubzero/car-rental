import { Link } from '@inertiajs/react';
import { TripForm } from '@/components/public/booking/trip-form';
import { PageHeader } from '@/components/public/page-header';
import { Seo } from '@/components/public/seo';
import { contact, fleet } from '@/routes';
import { store } from '@/routes/bookings';
import { show as carShow } from '@/routes/cars';
import type { CarCard, TripOptions } from '@/types';

/**
 * The booking page (M8.9): step 1 for a chosen car. Saving it starts a draft and moves to
 * the renter's own booking link.
 */
export default function BookingStart({
    car,
    bookable,
    options,
    reservation_percent: reservation,
    turnstile_site_key: siteKey,
}: {
    car: CarCard;
    bookable: boolean;
    options: TripOptions;
    reservation_percent: number;
    turnstile_site_key: string | null;
}) {
    return (
        <>
            <Seo
                title={`Book the ${car.name}`}
                description={`Choose your dates for the ${car.name}. Pay ${reservation}% to reserve, the rest at handover.`}
            />
            <PageHeader
                title={`Book the ${car.name}`}
                intro={`Step 1 of 3: your trip. Pay ${reservation}% to reserve; the balance is due at handover.`}
                crumbs={[
                    { label: 'Fleet', href: fleet().url },
                    { label: car.name, href: carShow(car.slug).url },
                ]}
            />
            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                {bookable ? (
                    <TripForm
                        carSlug={car.slug}
                        options={options}
                        action={{ url: store(car.slug).url, method: 'post' }}
                        turnstileSiteKey={siteKey}
                        submitLabel="Continue to driver details"
                    />
                ) : (
                    <div className="rounded-lg bg-surface p-8">
                        <p className="text-lg font-semibold text-foreground">
                            This car isn’t taking online bookings right now.
                        </p>
                        <p className="mt-2 text-muted-foreground">
                            Ask us about dates, or choose another car.
                        </p>
                        <div className="mt-6 flex gap-4 text-sm font-semibold text-gold">
                            <Link
                                href={contact({ query: { car: car.slug } }).url}
                            >
                                Ask about this car
                            </Link>
                            <Link href={fleet().url}>See the fleet</Link>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
