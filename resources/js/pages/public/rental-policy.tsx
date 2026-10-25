import type { ReactNode } from 'react';
import { PageHeader } from '@/components/public/page-header';
import { Seo } from '@/components/public/seo';
import type { Money, PageImage, PublicPolicy } from '@/types';

// Mirrors App\Http\Controllers\Public\RentalPolicyController.
type Props = {
    heading: string;
    intro: string | null;
    header_image: PageImage | null;
    groups: { key: string; label: string; policies: PublicPolicy[] }[];
    cancellation_tiers: {
        hours_before_pickup: number;
        refund_percent: number;
        label: string | null;
    }[];
    fees: {
        code: string;
        name: string;
        description: string | null;
        amount: Money | null;
        basis: string;
        payable_at: string;
    }[];
    deposit_bands: { ages: string; percent: number; label: string | null }[];
    delivery_zones: {
        name: string;
        description: string | null;
        fee: Money | null;
    }[];
};

const RATES_ID = 'rates-and-charges';

/**
 * The Rental Policy page (M7.14): every published policy, grouped, with an in-page index,
 * and the current fees, deposits, cancellation refunds and delivery charges.
 */
export default function RentalPolicy({
    heading,
    intro,
    header_image: headerImage,
    groups,
    cancellation_tiers: tiers,
    fees,
    deposit_bands: bands,
    delivery_zones: zones,
}: Props) {
    const hasRates =
        tiers.length + fees.length + bands.length + zones.length > 0;

    return (
        <>
            <Seo
                title={heading}
                description={
                    intro ??
                    'Booking, deposit, insurance, mileage, fuel and cancellation terms for renting with Baines Executives.'
                }
            />
            <PageHeader title={heading} intro={intro} image={headerImage} />

            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-14 sm:px-6 lg:grid-cols-12 lg:px-8">
                <nav aria-label="On this page" className="lg:col-span-3">
                    <div className="lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
                        <p className="eyebrow">On this page</p>
                        <ol className="mt-4 space-y-4 text-sm">
                            {groups.map((group) => (
                                <li key={group.key}>
                                    <a
                                        href={`#${group.key}`}
                                        className="font-semibold text-foreground hover:text-gold"
                                    >
                                        {group.label}
                                    </a>
                                    <ul className="mt-2 space-y-1.5 border-l border-white/10 pl-3">
                                        {group.policies.map((policy) => (
                                            <li key={policy.slug}>
                                                <a
                                                    href={`#${policy.slug}`}
                                                    className="text-muted-foreground hover:text-foreground"
                                                >
                                                    {policy.title}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </li>
                            ))}
                            {hasRates && (
                                <li>
                                    <a
                                        href={`#${RATES_ID}`}
                                        className="font-semibold text-foreground hover:text-gold"
                                    >
                                        Rates and charges
                                    </a>
                                </li>
                            )}
                        </ol>
                    </div>
                </nav>

                <div className="min-w-0 space-y-16 lg:col-span-9">
                    {groups.map((group) => (
                        <section
                            key={group.key}
                            id={group.key}
                            aria-labelledby={`${group.key}-title`}
                            className="scroll-mt-28"
                        >
                            <h2
                                id={`${group.key}-title`}
                                className="font-display text-3xl text-foreground"
                            >
                                {group.label}
                            </h2>
                            <div className="mt-6 divide-y divide-white/10 border-y border-white/10">
                                {group.policies.map((policy) => (
                                    <article
                                        key={policy.slug}
                                        id={policy.slug}
                                        className="scroll-mt-28 py-7"
                                    >
                                        <h3 className="text-xl font-semibold tracking-tight text-foreground">
                                            {policy.title}
                                        </h3>
                                        <div
                                            className="prose mt-3 max-w-none prose-invert prose-p:leading-relaxed prose-p:text-white/75 prose-a:text-gold prose-strong:text-white prose-li:text-white/75"
                                            // Rendered from Markdown on the server (D23).
                                            dangerouslySetInnerHTML={{
                                                __html: policy.body_html,
                                            }}
                                        />
                                    </article>
                                ))}
                            </div>
                        </section>
                    ))}

                    {hasRates && (
                        <section
                            id={RATES_ID}
                            aria-labelledby={`${RATES_ID}-title`}
                            className="scroll-mt-28 space-y-10"
                        >
                            <div>
                                <h2
                                    id={`${RATES_ID}-title`}
                                    className="font-display text-3xl text-foreground"
                                >
                                    Rates and charges
                                </h2>
                                <p className="mt-3 max-w-2xl text-muted-foreground">
                                    The amounts the booking form uses today.
                                    Prices are tax inclusive unless stated.
                                </p>
                            </div>

                            {fees.length > 0 && (
                                <RateTable
                                    title="Fees and extras"
                                    head={['Charge', 'Amount', 'Paid']}
                                >
                                    {fees.map((fee) => (
                                        <tr key={fee.code}>
                                            <td>
                                                <span className="font-medium text-foreground">
                                                    {fee.name}
                                                </span>
                                                {fee.description && (
                                                    <span className="mt-0.5 block text-sm text-muted-foreground">
                                                        {fee.description}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="tabular-nums">
                                                {fee.amount
                                                    ? `${fee.amount.display} ${fee.basis.toLowerCase()}`
                                                    : 'On request'}
                                            </td>
                                            <td>{fee.payable_at}</td>
                                        </tr>
                                    ))}
                                </RateTable>
                            )}

                            {bands.length > 0 && (
                                <RateTable
                                    title="Security deposit"
                                    head={['Driver age', 'Deposit']}
                                    note="Refundable, paid at handover, as a share of the car's value."
                                >
                                    {bands.map((band) => (
                                        <tr key={band.ages}>
                                            <td>
                                                {band.label ??
                                                    `Aged ${band.ages}`}
                                            </td>
                                            <td className="tabular-nums">
                                                {band.percent}% of the car's
                                                value
                                            </td>
                                        </tr>
                                    ))}
                                </RateTable>
                            )}

                            {tiers.length > 0 && (
                                <RateTable
                                    title="Cancellation"
                                    head={['Cancelled', 'Refund']}
                                >
                                    {tiers.map((tier) => (
                                        <tr key={tier.hours_before_pickup}>
                                            <td>
                                                {tier.label ??
                                                    `${tier.hours_before_pickup} hours or more before pick-up`}
                                            </td>
                                            <td className="tabular-nums">
                                                {tier.refund_percent}%
                                            </td>
                                        </tr>
                                    ))}
                                </RateTable>
                            )}

                            {zones.length > 0 && (
                                <RateTable
                                    title="Delivery and collection"
                                    head={['Area', 'Fee']}
                                >
                                    {zones.map((zone) => (
                                        <tr key={zone.name}>
                                            <td>
                                                <span className="font-medium text-foreground">
                                                    {zone.name}
                                                </span>
                                                {zone.description && (
                                                    <span className="mt-0.5 block text-sm text-muted-foreground">
                                                        {zone.description}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="tabular-nums">
                                                {zone.fee?.display ??
                                                    'Quoted when you book'}
                                            </td>
                                        </tr>
                                    ))}
                                </RateTable>
                            )}
                        </section>
                    )}
                </div>
            </div>
        </>
    );
}

function RateTable({
    title,
    head,
    note,
    children,
}: {
    title: string;
    head: string[];
    note?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <h3 className="text-lg font-semibold text-foreground">{title}</h3>
            {note && (
                <p className="mt-1 text-sm text-muted-foreground">{note}</p>
            )}
            {/* Scrolls sideways on phones, so keyboard users can focus it to scroll. */}
            <div
                className="mt-4 overflow-x-auto rounded-lg border border-white/10 focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                role="region"
                aria-label={title}
                tabIndex={0}
            >
                <table className="w-full min-w-[28rem] text-left text-sm text-white/80 [&_td]:border-t [&_td]:border-white/10 [&_td]:px-4 [&_td]:py-3 [&_td]:align-top">
                    <thead className="bg-surface text-xs text-muted-foreground">
                        <tr>
                            {head.map((label) => (
                                <th
                                    key={label}
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>{children}</tbody>
                </table>
            </div>
        </div>
    );
}
