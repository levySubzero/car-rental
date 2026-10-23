import { useForm } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import BookingPaymentController from '@/actions/App/Http/Controllers/Public/BookingPaymentController';
import { MediaUploader } from '@/components/media/media-uploader';
import { Field, inputClass } from '@/components/public/form-field';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type {
    MediaCollectionLimits,
    MediaItem,
    PublicPaymentMethod,
} from '@/types';

/**
 * Steps 4 and 5 (M9.2, M9.3): choose how to pay, then send the confirmation message and a
 * proof if wanted. A method's instructions show only once it is chosen (PRD P-1). Cash
 * has nothing to send: the renter pays at the office.
 */
export function PaymentForm({
    token,
    reference,
    methods,
    proofLimits,
}: {
    token: string;
    reference: string | null;
    methods: PublicPaymentMethod[];
    proofLimits: MediaCollectionLimits;
}) {
    const form = useForm<{
        method: string;
        transaction_message: string;
        proof: MediaItem | null;
    }>({ method: '', transaction_message: '', proof: null });
    const chosen = methods.find((method) => method.code === form.data.method);
    const isCash = chosen?.code === 'cash';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            method: data.method,
            transaction_message: isCash ? null : data.transaction_message,
            proof: isCash ? null : (data.proof?.uuid ?? null),
        }));
        form.post(BookingPaymentController.store(token).url, {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-6" noValidate>
            <fieldset className="grid gap-3">
                <legend className="mb-2 text-sm font-medium text-white/85">
                    How will you pay?
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {methods.map((method) => (
                        <label
                            key={method.code}
                            className={cn(
                                'flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-4 py-3 text-sm text-white/85 transition-colors',
                                form.data.method === method.code
                                    ? 'border-gold bg-gold/10 text-foreground'
                                    : 'border-white/15 hover:border-white/30',
                            )}
                        >
                            <input
                                type="radio"
                                name="method"
                                value={method.code}
                                checked={form.data.method === method.code}
                                onChange={() => {
                                    form.setData('method', method.code);
                                    form.clearErrors();
                                }}
                                className="size-4 accent-[var(--color-gold)]"
                            />
                            {method.name}
                        </label>
                    ))}
                </div>
                {form.errors.method && (
                    <p className="text-sm text-red-400">{form.errors.method}</p>
                )}
            </fieldset>

            {chosen && (
                <div className="grid gap-6">
                    <div className="rounded-md border border-white/10 bg-ink/40 p-4">
                        <h3 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <Banknote
                                aria-hidden
                                className="size-4 text-gold"
                            />
                            Paying by {chosen.name}
                        </h3>
                        <div
                            className="prose prose-sm mt-2 max-w-none prose-invert prose-p:text-white/75 prose-a:text-gold prose-strong:text-white prose-li:text-white/75"
                            dangerouslySetInnerHTML={{
                                __html: chosen.instructions_html,
                            }}
                        />
                        {reference && !isCash && (
                            <p className="mt-3 text-sm text-white/75">
                                Use your booking reference{' '}
                                <strong className="text-foreground">
                                    {reference}
                                </strong>{' '}
                                as the payment reference.
                            </p>
                        )}
                    </div>

                    {!isCash && (
                        <>
                            <Field
                                label="Confirmation message"
                                hint="Paste the SMS, transfer confirmation or receipt number you received."
                                error={form.errors.transaction_message}
                            >
                                {(id, describedBy, invalid) => (
                                    <textarea
                                        id={id}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        rows={4}
                                        maxLength={1000}
                                        required={
                                            chosen.requires_transaction_message
                                        }
                                        value={form.data.transaction_message}
                                        onChange={(event) =>
                                            form.setData(
                                                'transaction_message',
                                                event.target.value,
                                            )
                                        }
                                        className={cn(
                                            inputClass,
                                            'h-auto py-2',
                                        )}
                                    />
                                )}
                            </Field>

                            <div className="grid gap-2">
                                <p className="text-sm font-medium text-white/85">
                                    Screenshot or receipt
                                    {!chosen.requires_proof && (
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            (optional)
                                        </span>
                                    )}
                                </p>
                                <MediaUploader
                                    limits={{ ...proofLimits, max_files: 1 }}
                                    value={
                                        form.data.proof ? [form.data.proof] : []
                                    }
                                    onChange={(items) =>
                                        form.setData((data) => ({
                                            ...data,
                                            proof: items[0] ?? null,
                                        }))
                                    }
                                    bookingToken={token}
                                    error={form.errors.proof}
                                />
                            </div>
                        </>
                    )}

                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={form.processing}
                            className="h-12 px-8 font-semibold"
                        >
                            {form.processing
                                ? 'Sending…'
                                : isCash
                                  ? "I'll pay cash at the office"
                                  : 'Send payment details'}
                        </Button>
                    </div>
                </div>
            )}
        </form>
    );
}
