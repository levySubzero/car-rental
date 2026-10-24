import { router, useForm, useHttp } from '@inertiajs/react';
import { CreditCard, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import OnlinePaymentController from '@/actions/App/Http/Controllers/Public/OnlinePaymentController';
import { Field, inputClass } from '@/components/public/form-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { MpesaPayTo, OnlinePaymentAttempt } from '@/types';

// After this long without an answer the renter may ask for the prompt again.
const RESEND_AFTER_MS = 90_000;

/**
 * Pay by M-Pesa from the page (M11B.7): an STK push to the renter's phone, then wait while
 * the status page polls. A failed push says why and can be sent again; the paybill line is
 * for renters who prefer to pay by hand, with the account number that matches the booking.
 */
export function MpesaOnline({
    token,
    amount,
    phone,
    attempt,
    payTo,
}: {
    token: string;
    amount: string | null;
    phone: string | null;
    attempt: OnlinePaymentAttempt | null;
    payTo: MpesaPayTo | null;
}) {
    const form = useForm({ phone: phone ?? '' });
    const mine = attempt?.gateway === 'mpesa_stk' ? attempt : null;
    const waiting = mine?.status === 'initiated';
    const canResend = useElapsed(mine?.started_at ?? null, RESEND_AFTER_MS);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(OnlinePaymentController.mpesa(token).url, {
            preserveScroll: true,
        });
    };

    return (
        <div className="grid gap-4">
            {waiting ? (
                <div
                    role="status"
                    className="flex items-start gap-3 rounded-md border border-gold/40 bg-gold/10 p-4 text-sm text-white/85"
                >
                    <Spinner className="mt-0.5 size-4 shrink-0 text-gold" />
                    <p>
                        Check your phone and enter your M-Pesa PIN to pay
                        {amount ? ` ${amount}` : ''}. This page updates once the
                        payment comes through.
                    </p>
                </div>
            ) : (
                mine?.status === 'failed' && (
                    <p
                        role="alert"
                        className="rounded-md border border-red-400/40 bg-red-400/10 px-3 py-2 text-sm text-red-200"
                    >
                        The M-Pesa payment did not go through
                        {mine.failure_reason ? `: ${mine.failure_reason}` : ''}.
                        You can try again.
                    </p>
                )
            )}

            {(!waiting || canResend) && (
                <form
                    onSubmit={submit}
                    className="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end"
                    noValidate
                >
                    <Field
                        label="M-Pesa phone number"
                        hint="We send a payment request to this phone."
                        error={form.errors.phone}
                    >
                        {(id, describedBy, invalid) => (
                            <input
                                id={id}
                                type="tel"
                                inputMode="tel"
                                autoComplete="tel"
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                                className={inputClass}
                            />
                        )}
                    </Field>
                    <Button
                        type="submit"
                        size="lg"
                        disabled={form.processing}
                        className="h-12 px-6 font-semibold"
                    >
                        <Smartphone aria-hidden className="size-4" />
                        {form.processing
                            ? 'Sending…'
                            : waiting
                              ? 'Send the request again'
                              : `Pay${amount ? ` ${amount}` : ''} now`}
                    </Button>
                </form>
            )}

            {payTo && (
                <p className="text-sm text-white/75">
                    Prefer to pay yourself? Use{' '}
                    {payTo.till ? 'till number' : 'paybill'}{' '}
                    <strong className="text-foreground">{payTo.number}</strong>
                    {payTo.account && (
                        <>
                            , account number{' '}
                            <strong className="text-foreground">
                                {payTo.account}
                            </strong>
                            . We match it to your booking automatically
                        </>
                    )}
                    .
                </p>
            )}
        </div>
    );
}

/**
 * Pay by card in Paystack's popup (M11B.7). The server opens the transaction; when the popup
 * reports success the server checks it with Paystack before the booking is confirmed.
 */
export function CardOnline({
    token,
    amount,
    attempt,
}: {
    token: string;
    amount: string | null;
    attempt: OnlinePaymentAttempt | null;
}) {
    const http = useHttp<
        Record<string, never>,
        { access_code: string; reference: string; public_key: string }
    >({});
    const [message, setMessage] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const mine = attempt?.gateway === 'paystack' ? attempt : null;

    const pay = async () => {
        setMessage(null);

        try {
            const started = await http.post(
                OnlinePaymentController.card(token).url,
            );

            if (!started) {
                setMessage(
                    Object.values(http.errors)[0] ??
                        'Card payment could not be started. Please try again.',
                );

                return;
            }

            const { default: PaystackPop } =
                await import('@paystack/inline-js');

            new PaystackPop().resumeTransaction(started.access_code, {
                onSuccess: (transaction) => {
                    setConfirming(true);
                    router.post(
                        OnlinePaymentController.confirmCard(token).url,
                        { reference: transaction.reference },
                        {
                            preserveScroll: true,
                            onFinish: () => setConfirming(false),
                        },
                    );
                },
                onCancel: () =>
                    setMessage('Card payment cancelled. Nothing was charged.'),
                onError: (error) => setMessage(error.message),
            });
        } catch {
            setMessage('Card payment could not be started. Please try again.');
        }
    };

    return (
        <div className="grid gap-4">
            {mine?.status === 'failed' && !message && (
                <p
                    role="alert"
                    className="rounded-md border border-red-400/40 bg-red-400/10 px-3 py-2 text-sm text-red-200"
                >
                    The last card payment did not go through. You can try again.
                </p>
            )}
            {message && (
                <p role="alert" className="text-sm text-red-300">
                    {message}
                </p>
            )}
            <div>
                <Button
                    type="button"
                    size="lg"
                    onClick={pay}
                    disabled={http.processing || confirming}
                    className="h-12 px-6 font-semibold"
                >
                    <CreditCard aria-hidden className="size-4" />
                    {confirming
                        ? 'Confirming…'
                        : http.processing
                          ? 'Opening…'
                          : `Pay${amount ? ` ${amount}` : ''} by card`}
                </Button>
                <p className="mt-2 text-xs text-muted-foreground">
                    Visa or Mastercard, processed securely by Paystack.
                </p>
            </div>
        </div>
    );
}

/**
 * True once `ms` have passed since `since` (an ISO time), re-checked every few seconds.
 */
function useElapsed(since: string | null, ms: number): boolean {
    // Zero until mounted, so the server render and hydration agree.
    const [now, setNow] = useState(0);

    useEffect(() => {
        if (!since) {
            return;
        }

        setNow(Date.now());
        const timer = window.setInterval(() => setNow(Date.now()), 5_000);

        return () => window.clearInterval(timer);
    }, [since]);

    return since !== null && now > 0 && now - new Date(since).getTime() >= ms;
}
