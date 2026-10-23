import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Field, inputClass } from '@/components/public/form-field';
import { Turnstile } from '@/components/public/turnstile';
import { Button } from '@/components/ui/button';
import { code, restart, verify } from '@/routes/portal';

type Props = {
    step: 'identify' | 'code';
    identifier: string | null;
    turnstile_site_key: string | null;
};

/**
 * My bookings, signing in (M10B): the email or phone the renter booked with, then the
 * 6-digit code emailed to the address on their bookings.
 */
export default function MyBookingsSignIn({
    step,
    identifier,
    turnstile_site_key: siteKey,
}: Props) {
    const identify = useForm({
        identifier: identifier ?? '',
        'cf-turnstile-response': '',
    });
    const confirm = useForm({ code: '' });

    const sendCode = (event: FormEvent) => {
        event.preventDefault();
        identify.post(code().url, { preserveScroll: true });
    };

    const checkCode = (event: FormEvent) => {
        event.preventDefault();
        confirm.post(verify().url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="My bookings">
                <meta
                    head-key="robots"
                    name="robots"
                    content="noindex, nofollow"
                />
            </Head>

            <section className="tone-charcoal">
                <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <h1 className="font-display text-3xl text-foreground sm:text-4xl">
                        My bookings
                    </h1>
                    <p className="mt-3 max-w-xl text-white/80">
                        See your bookings, download your voucher, or ask us to
                        change or cancel a booking.
                    </p>
                </div>
            </section>

            <div className="mx-auto max-w-xl px-4 py-12 sm:px-6">
                {step === 'identify' ? (
                    <form
                        onSubmit={sendCode}
                        noValidate
                        className="grid gap-5 rounded-lg border border-white/10 bg-surface p-6"
                    >
                        <Field
                            label="Email or phone"
                            error={identify.errors.identifier}
                            hint="The one you gave for the main driver when you booked."
                        >
                            {(id, describedBy, invalid) => (
                                <input
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    required
                                    autoComplete="email"
                                    value={identify.data.identifier}
                                    onChange={(event) =>
                                        identify.setData(
                                            'identifier',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            )}
                        </Field>
                        <div className="grid gap-2">
                            <Turnstile
                                siteKey={siteKey}
                                onToken={(token) =>
                                    identify.setData(
                                        'cf-turnstile-response',
                                        token,
                                    )
                                }
                            />
                            {identify.errors['cf-turnstile-response'] && (
                                <p className="text-sm text-red-400">
                                    {identify.errors['cf-turnstile-response']}
                                </p>
                            )}
                        </div>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={identify.processing}
                            className="h-12 font-semibold"
                        >
                            {identify.processing
                                ? 'Sending…'
                                : 'Email me a code'}
                        </Button>
                    </form>
                ) : (
                    <form
                        onSubmit={checkCode}
                        noValidate
                        className="grid gap-5 rounded-lg border border-white/10 bg-surface p-6"
                    >
                        <p className="text-sm text-white/80">
                            If{' '}
                            <strong className="text-foreground">
                                {identifier}
                            </strong>{' '}
                            matches a booking, we have emailed a 6-digit code to
                            the address on it. It expires in 10 minutes.
                        </p>
                        <Field label="Code" error={confirm.errors.code}>
                            {(id, describedBy, invalid) => (
                                <input
                                    id={id}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    required
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    value={confirm.data.code}
                                    onChange={(event) =>
                                        confirm.setData(
                                            'code',
                                            event.target.value.replace(
                                                /\D/g,
                                                '',
                                            ),
                                        )
                                    }
                                    className={`${inputClass} text-lg tracking-[0.4em] tabular-nums`}
                                />
                            )}
                        </Field>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={confirm.processing}
                            className="h-12 font-semibold"
                        >
                            Show my bookings
                        </Button>
                        <button
                            type="button"
                            onClick={() => router.post(restart().url)}
                            className="justify-self-start text-sm text-white/70 underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Use a different email or phone
                        </button>
                    </form>
                )}
            </div>
        </>
    );
}
