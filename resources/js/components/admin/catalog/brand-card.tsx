import { Link } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { Badge } from '@/components/ui/badge';
import type { AdminBrand } from '@/types';

/**
 * A brand in the admin grid view: logo, car count, status and the marquee switch.
 */
export function BrandCard({
    brand,
    canUpdate,
}: {
    brand: AdminBrand;
    canUpdate: boolean;
}) {
    const logo = brand.logo?.urls.card ?? brand.logo?.urls.original;

    return (
        <article className="flex h-full flex-col overflow-hidden rounded-lg border bg-card">
            <Link
                href={BrandController.edit(brand.slug)}
                className="flex aspect-[3/2] items-center justify-center bg-muted p-6"
            >
                {logo ? (
                    <img
                        src={logo}
                        alt={brand.logo?.alt ?? brand.name}
                        loading="lazy"
                        className="max-h-full max-w-full object-contain"
                    />
                ) : (
                    <ImageOff className="size-6 text-muted-foreground" />
                )}
            </Link>
            <div className="flex flex-1 flex-col gap-3 p-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="grid min-w-0">
                        <Link
                            href={BrandController.edit(brand.slug)}
                            className="truncate font-medium hover:underline"
                        >
                            {brand.name}
                        </Link>
                        <span className="text-xs text-muted-foreground">
                            {brand.cars_count ?? 0} car(s)
                        </span>
                    </div>
                    {brand.is_active ? (
                        <Badge variant="secondary">Active</Badge>
                    ) : (
                        <Badge variant="outline">Hidden</Badge>
                    )}
                </div>
                <label className="mt-auto flex items-center justify-between gap-2 border-t pt-3 text-xs text-muted-foreground">
                    In marquee
                    <FlagSwitch
                        key={`${brand.id}-${brand.show_in_marquee}`}
                        url={BrandController.marquee.url(brand.slug)}
                        checked={brand.show_in_marquee}
                        label={`Show ${brand.name} in the marquee`}
                        disabled={!canUpdate}
                    />
                </label>
            </div>
        </article>
    );
}
