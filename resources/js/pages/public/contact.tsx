import { useForm } from '@inertiajs/react';
import { CheckCircle2, Mail, MapPin, Phone } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Field, inputClass } from '@/components/public/form-field';
import { BrandIcon } from '@/components/public/icons';
import { PageHeader } from '@/components/public/page-header';
import { Seo } from '@/components/public/seo';
import { Turnstile } from '@/components/public/turnstile';
import { Button } from '@/components/ui/button';
import { useSite } from '@/hooks/use-site';
import { businessJsonLd } from '@/lib/structured-data';
import { cn } from '@/lib/utils';
import { store } from '@/routes/contact';
import type { PageImage } from '@/types';

type Props = {
    heading: string;
    intro: string | null;
    header_image: PageImage | null;
    cars: { name: string; slug: string }[];
    selected_car: string | null;
    sent: boolean;
    turnstile_site_key: string | null;
};

/**
 * The Contact page (M7.15): the enquiry form, which lands in Admin → Contact messages,
 * beside the phone, WhatsApp, email, address and map.
 */
export default function Contact({
    heading,
    intro,
    header_image: headerImage,
    cars,
    selected_car: selectedCar,
    sent,
    turnstile_site_key: siteKey,
}: Props) {
    const { site, phone, telHref, whatsapp } = useSite();
    const chat = whatsapp();
    const [showForm, setShowForm] = useState(!sent);

    const form = useForm({
        name: '',
        email: '',
        phone: '',
        car: selectedCar ?? '',
        preferred_dates: '',
        message: '',
        'cf-turnstile-response': '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
        });
    };

    return (
        <>
            <Seo
                title={heading}
                description={
                    intro ??
                    `Call, WhatsApp or message ${site.company_name}. Office at ${site.address ?? 'Kilimani, Nairobi'}.`
                }
                jsonLd={businessJsonLd(site)}
            />
            <PageHeader
                title={heading}
                intro={
                    intro ??
                    'Tell us the car and your dates, or ask us anything. We reply by WhatsApp, phone or email.'
                }
                image={headerImage}
            />

            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-12 lg:px-8">
                <section
                    aria-labelledby="form-title"
                    className="min-w-0 lg:col-span-7"
                >
                    <h2
                        id="form-title"
                        className="font-display text-2xl text-foreground"
                    >
                        Send us a message
                    </h2>

                    {!showForm ? (
                        <div
                            role="status"
                            className="mt-6 rounded-lg border border-gold/40 bg-surface p-8"
                        >
                            <CheckCircle2
                                aria-hidden
                                className="size-8 text-gold"
                            />
                            <p className="mt-4 text-xl font-semibold text-foreground">
                                Thank you. Your message has been sent.
                            </p>
                            <p className="mt-2 text-muted-foreground">
                                We will reply by WhatsApp, phone or email,
                                usually the same day. For anything urgent, call
                                us.
                            </p>
                            <button
                                type="button"
                                onClick={() => setShowForm(true)}
                                className="mt-6 text-sm font-semibold text-gold underline-offset-4 hover:underline"
                            >
                                Send another message
                            </button>
                        </div>
                    ) : (
                        <form
                            onSubmit={submit}
                            noValidate
                            className="mt-6 grid gap-5 sm:grid-cols-2"
                        >
                            <Field
                                label="Your name"
                                error={form.errors.name}
                                className="sm:col-span-2"
                            >
                                {(id, describedBy, invalid) => (
                                    <input
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        required
                                        autoComplete="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        className={inputClass}
                                    />
                                )}
                            </Field>
                            <Field
                                label="Email"
                                error={form.errors.email}
                                hint="Email or phone, so we can reply"
                            >
                                {(id, describedBy, invalid) => (
                                    <input
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        type="email"
                                        autoComplete="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                )}
                            </Field>
                            <Field
                                label="Phone or WhatsApp"
                                error={form.errors.phone}
                            >
                                {(id, describedBy, invalid) => (
                                    <input
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        type="tel"
                                        autoComplete="tel"
                                        placeholder="+254 7…"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                )}
                            </Field>
                            <Field
                                label="Car (optional)"
                                error={form.errors.car}
                            >
                                {(id, describedBy, invalid) => (
                                    <select
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        value={form.data.car}
                                        onChange={(e) =>
                                            form.setData('car', e.target.value)
                                        }
                                        className={inputClass}
                                    >
                                        <option value="">Any car</option>
                                        {cars.map((car) => (
                                            <option
                                                key={car.slug}
                                                value={car.slug}
                                            >
                                                {car.name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                            </Field>
                            <Field
                                label="Dates (optional)"
                                error={form.errors.preferred_dates}
                            >
                                {(id, describedBy, invalid) => (
                                    <input
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        placeholder="For example 3 to 6 November"
                                        value={form.data.preferred_dates}
                                        onChange={(e) =>
                                            form.setData(
                                                'preferred_dates',
                                                e.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                )}
                            </Field>
                            <Field
                                label="Message"
                                error={form.errors.message}
                                className="sm:col-span-2"
                            >
                                {(id, describedBy, invalid) => (
                                    <textarea
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        required
                                        rows={5}
                                        value={form.data.message}
                                        onChange={(e) =>
                                            form.setData(
                                                'message',
                                                e.target.value,
                                            )
                                        }
                                        className={cn(
                                            inputClass,
                                            'h-auto py-3',
                                        )}
                                    />
                                )}
                            </Field>
                            <div className="grid gap-2 sm:col-span-2">
                                <Turnstile
                                    siteKey={siteKey}
                                    onToken={(token) =>
                                        form.setData(
                                            'cf-turnstile-response',
                                            token,
                                        )
                                    }
                                />
                                {form.errors['cf-turnstile-response'] && (
                                    <p className="text-sm text-red-400">
                                        {form.errors['cf-turnstile-response']}
                                    </p>
                                )}
                            </div>
                            <div className="sm:col-span-2">
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={form.processing}
                                    className="h-12 px-8 font-semibold"
                                >
                                    {form.processing
                                        ? 'Sending…'
                                        : 'Send message'}
                                </Button>
                            </div>
                        </form>
                    )}
                </section>

                <aside
                    aria-label="Contact details"
                    className="min-w-0 lg:col-span-5"
                >
                    <div className="space-y-6 rounded-lg bg-surface p-7">
                        {chat && (
                            <Button
                                asChild
                                size="lg"
                                className="h-12 w-full bg-[#25D366] font-semibold text-ink hover:bg-[#25D366]/90"
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
                                    Chat on WhatsApp
                                </a>
                            </Button>
                        )}
                        <ul className="space-y-4 text-white/80">
                            {phone && telHref && (
                                <Detail
                                    icon={<Phone className="size-5" />}
                                    label="Call"
                                >
                                    <a
                                        href={telHref}
                                        className="hover:text-gold"
                                    >
                                        {phone}
                                    </a>
                                </Detail>
                            )}
                            {site.email && (
                                <Detail
                                    icon={<Mail className="size-5" />}
                                    label="Email"
                                >
                                    <a
                                        href={`mailto:${site.email}`}
                                        className="[overflow-wrap:anywhere] hover:text-gold"
                                    >
                                        {site.email}
                                    </a>
                                </Detail>
                            )}
                            {site.address && (
                                <Detail
                                    icon={<MapPin className="size-5" />}
                                    label="Office"
                                >
                                    <address className="not-italic">
                                        {site.address}
                                    </address>
                                </Detail>
                            )}
                        </ul>
                        {site.map_embed_url && (
                            <iframe
                                src={site.map_embed_url}
                                title={`Map: ${site.address ?? site.company_name}`}
                                loading="lazy"
                                referrerPolicy="no-referrer-when-downgrade"
                                className="h-64 w-full rounded-md border border-white/10 grayscale hue-rotate-180 invert-[0.9]"
                            />
                        )}
                    </div>
                </aside>
            </div>
        </>
    );
}

function Detail({
    icon,
    label,
    children,
}: {
    icon: ReactNode;
    label: string;
    children: ReactNode;
}) {
    return (
        <li className="flex gap-3">
            <span aria-hidden className="mt-0.5 text-gold">
                {icon}
            </span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <div className="mt-0.5">{children}</div>
            </div>
        </li>
    );
}
