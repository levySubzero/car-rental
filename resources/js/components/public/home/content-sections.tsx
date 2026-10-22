import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BadgeCheck,
    CalendarCheck,
    CalendarX,
    CarFront,
    ContactRound,
    CreditCard,
    Fuel,
    Gauge,
    IdCard,
    Info,
    KeyRound,
    MapPinXInside,
    Phone,
    Receipt,
    ShieldCheck,
    UserRound,
    UserRoundCheck,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { BrandIcon } from '@/components/public/icons';
import { SectionHeading } from '@/components/public/section-heading';
import { Button } from '@/components/ui/button';
import { useSite } from '@/hooks/use-site';
import { rentalPolicy } from '@/routes';
import type { PublicListItem } from '@/types';
import type { HomeContent, HomeProps, TrustFigure } from './types';

const container = 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8';

export function About({ content }: { content: HomeContent | null }) {
    if (!content?.about_heading && !content?.about_body_html) {
        return null;
    }

    return (
        <section
            aria-labelledby="about-heading"
            className={`${container} grid items-center gap-14 py-24 lg:grid-cols-2`}
        >
            <div className="lg:order-2">
                <SectionHeading
                    id="about-heading"
                    title={content.about_heading ?? 'About us'}
                />
                <div
                    className="prose mt-6 max-w-none prose-p:leading-relaxed prose-p:text-muted-foreground prose-a:text-accent-gold prose-strong:text-foreground"
                    // Rendered from Markdown on the server (D23).
                    dangerouslySetInnerHTML={{
                        __html: content.about_body_html,
                    }}
                />
            </div>
            <div className="relative">
                {content.about_image?.urls.card ? (
                    <img
                        src={content.about_image.urls.card}
                        srcSet={[
                            content.about_image.urls.card &&
                                `${content.about_image.urls.card} 800w`,
                            content.about_image.urls.full &&
                                `${content.about_image.urls.full} 1920w`,
                        ]
                            .filter(Boolean)
                            .join(', ')}
                        sizes="(min-width: 1024px) 50vw, 100vw"
                        alt={content.about_image.alt ?? ''}
                        loading="lazy"
                        className="aspect-4/3 w-full rounded-md object-cover"
                    />
                ) : (
                    // Until staff add an About image (Content → Pages → Home).
                    <div
                        aria-hidden
                        className="flex aspect-4/3 w-full flex-col justify-end rounded-md border border-white/10 bg-surface bg-[radial-gradient(ellipse_at_top_left,rgb(200_164_90/0.18),transparent_60%)] p-10"
                    >
                        <p className="text-sm text-white/60">
                            Since 2023, from Kilimani, Nairobi
                        </p>
                        <p className="mt-4 font-display text-4xl leading-tight text-white/90 sm:text-5xl">
                            Executive cars.
                            <br />
                            Chauffeurs.
                            <br />
                            Safaris.
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}

const POLICY_ICONS: Record<string, LucideIcon> = {
    'booking-and-payment': CreditCard,
    'insurance-covers-and-waivers': ShieldCheck,
    'included-at-no-extra-cost': BadgeCheck,
    mileage: Gauge,
    'licence-requirements': IdCard,
    'forms-of-identification': ContactRound,
    'age-requirements': UserRound,
    'deposit-and-excess': Wallet,
    'fuel-policy': Fuel,
    'cross-border': MapPinXInside,
    'additional-drivers': UsersRound,
    'chauffeur-service': UserRoundCheck,
    'cancellations-no-shows-amendments': CalendarX,
};

// App\Enums\PolicyGroup
const POLICY_GROUP_ICONS: Record<string, LucideIcon> = {
    booking: CalendarCheck,
    rental: CarFront,
    pickup: KeyRound,
    deposit: Wallet,
    cover: ShieldCheck,
    charges: Receipt,
};

function PolicyIcon({ slug, group }: { slug: string; group: string }) {
    const Icon = POLICY_ICONS[slug] ?? POLICY_GROUP_ICONS[group] ?? Info;

    return (
        <span
            aria-hidden
            className="flex size-11 shrink-0 items-center justify-center rounded-full bg-[color-mix(in_oklab,var(--accent-gold)_14%,transparent)] text-accent-gold"
        >
            <Icon className="size-5" strokeWidth={1.75} />
        </span>
    );
}

export function PolicyHighlights({
    policies,
}: {
    policies: HomeProps['policies'];
}) {
    if (policies.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="policies-heading">
            <div className={`${container} py-24`}>
                <SectionHeading id="policies-heading" title="Before you book" />
                <ul className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {policies.map((policy) => (
                        <li
                            key={policy.slug}
                            className="flex gap-4 rounded-md border border-border bg-card p-6"
                        >
                            <PolicyIcon
                                slug={policy.slug}
                                group={policy.group}
                            />
                            <div className="min-w-0">
                                <h3 className="text-lg font-semibold tracking-tight text-foreground">
                                    {policy.title}
                                </h3>
                                {policy.summary && (
                                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                                        {policy.summary}
                                    </p>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
                <Link
                    href={rentalPolicy()}
                    className="mt-10 inline-block text-sm font-semibold text-accent-gold underline-offset-4 hover:underline"
                >
                    Read the full rental policy
                </Link>
            </div>
        </section>
    );
}

function NumberedList({
    items,
    columns,
}: {
    items: PublicListItem[];
    columns: string;
}) {
    return (
        <ol className={`mt-14 grid gap-10 ${columns}`}>
            {items.map((item, index) => (
                <li
                    key={index}
                    className="relative border-t border-border pt-6"
                >
                    <span
                        aria-hidden
                        className="font-display text-4xl text-accent-gold tabular-nums"
                    >
                        {String(index + 1).padStart(2, '0')}
                    </span>
                    <h3 className="mt-4 text-lg font-semibold text-foreground">
                        {item.title}
                    </h3>
                    <p className="mt-2 leading-relaxed text-muted-foreground">
                        {item.body}
                    </p>
                </li>
            ))}
        </ol>
    );
}

const filled = (items: PublicListItem[] | undefined) =>
    (items ?? []).filter((item) => item.title || item.body);

export function Requirements({ content }: { content: HomeContent | null }) {
    const items = filled(content?.requirements);

    if (items.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="requirements-heading"
            className={`${container} py-24`}
        >
            <SectionHeading
                id="requirements-heading"
                title="What you need to rent"
            />
            <NumberedList items={items} columns="md:grid-cols-3" />
        </section>
    );
}

export function WhyChooseUs({
    items: list,
}: {
    items: PublicListItem[] | undefined;
}) {
    const items = filled(list);

    if (items.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="why-heading">
            <div className={`${container} py-24`}>
                <SectionHeading
                    id="why-heading"
                    title="Why Baines Executives"
                />
                <ul className="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    {items.map((item, index) => (
                        <li key={index} className="border-t border-border pt-6">
                            <h3 className="text-lg font-semibold tracking-tight text-foreground">
                                {item.title}
                            </h3>
                            <p className="mt-2 leading-relaxed text-muted-foreground">
                                {item.body}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

export function TrustStrip({ figures }: { figures: TrustFigure[] }) {
    if (figures.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Baines in numbers"
            className={`${container} py-16`}
        >
            <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-md border border-border bg-border lg:grid-cols-4">
                {figures.map((figure) => (
                    <div
                        key={figure.label}
                        className="flex flex-col-reverse bg-background px-6 py-8 text-center"
                    >
                        <dt className="mt-2 text-sm font-medium text-muted-foreground">
                            {figure.label}
                        </dt>
                        <dd className="font-display text-4xl text-foreground tabular-nums sm:text-5xl">
                            {figure.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export function ContactCta({ content }: { content: HomeContent | null }) {
    const { telHref, phone, whatsapp } = useSite();
    const chat = whatsapp();

    return (
        <section
            aria-labelledby="contact-heading"
            className="relative isolate overflow-hidden border-t border-white/5"
        >
            <div
                aria-hidden
                className="absolute inset-0 -z-10 bg-surface bg-[radial-gradient(ellipse_60%_80%_at_50%_120%,rgb(200_164_90/0.25),transparent_70%)]"
            />
            <div className={`${container} py-24 text-center`}>
                <SectionHeading
                    id="contact-heading"
                    align="center"
                    title={content?.contact_heading || 'Ready when you are'}
                >
                    {content?.contact_body && <p>{content.contact_body}</p>}
                </SectionHeading>
                <div className="mt-10 flex flex-wrap justify-center gap-3">
                    {chat && (
                        <Button
                            asChild
                            size="lg"
                            className="h-12 px-7 text-base font-semibold"
                        >
                            <a
                                href={chat}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <BrandIcon name="whatsapp" className="size-4" />
                                WhatsApp us
                            </a>
                        </Button>
                    )}
                    {telHref && (
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="h-12 border-white/25 bg-transparent px-7 text-base text-white hover:border-gold hover:bg-white/5 hover:text-white"
                        >
                            <a href={telHref}>
                                <Phone className="size-4" />
                                {phone}
                            </a>
                        </Button>
                    )}
                </div>
            </div>
        </section>
    );
}
