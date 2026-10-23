import { useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import PaymentSubmissionController from '@/actions/App/Http/Controllers/Admin/PaymentSubmissionController';
import {
    MoneyField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';
import type { AdminPayment } from '@/types';

/**
 * Verify a renter payment (M9.5): the reference and amount from the statement. The amount
 * starts at the reservation due; less than that is a part payment, which needs
 * payments.accept-partial and a note. Reused by the bookings desk (M11.4).
 */
export function VerifyPaymentDialog({ payment }: { payment: AdminPayment }) {
    const [open, setOpen] = useState(false);
    const can = useCan();
    const due = payment.booking.reservation;
    const form = useForm({
        reference: '',
        amount: due.major,
        note: '',
    });
    const amount = Number(form.data.amount);
    const partial =
        form.data.amount !== '' &&
        !Number.isNaN(amount) &&
        amount < Number(due.major);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(PaymentSubmissionController.verify.url(payment.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Check /> Verify
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Verify {payment.booking.reference}
                        </DialogTitle>
                        <DialogDescription>
                            {payment.method === 'cash'
                                ? 'Record the cash received at the office.'
                                : `Check the ${payment.method_name} statement, then enter what was received.`}{' '}
                            The booking is reserved and the car firmly booked.
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        name="reference"
                        label={
                            payment.method === 'cash'
                                ? 'Receipt number'
                                : 'Transaction reference'
                        }
                        required
                        autoComplete="off"
                        value={form.data.reference}
                        error={form.errors.reference}
                        onChange={(event) =>
                            form.setData('reference', event.target.value)
                        }
                    />
                    <MoneyField
                        name="amount"
                        label="Amount received"
                        required
                        value={form.data.amount}
                        error={form.errors.amount}
                        description={`Reservation due: ${due.display}`}
                        onChange={(event) =>
                            form.setData('amount', event.target.value)
                        }
                    />
                    {partial && (
                        <TextareaField
                            name="note"
                            label="Why accept a part payment?"
                            required
                            rows={3}
                            value={form.data.note}
                            error={form.errors.note}
                            description={
                                can('payments.accept-partial')
                                    ? 'The shortfall is added to the balance due at handover.'
                                    : 'You need the accept-partial permission to accept less than the reservation.'
                            }
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                    )}
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                (partial && !can('payments.accept-partial'))
                            }
                        >
                            Verify and reserve
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Reject a payment that can't be matched. The renter sees the reason and can pay again.
 */
export function RejectPaymentDialog({ payment }: { payment: AdminPayment }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(PaymentSubmissionController.reject.url(payment.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <X /> Reject
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Reject the payment for {payment.booking.reference}
                        </DialogTitle>
                        <DialogDescription>
                            The renter sees your reason and can pay again; the
                            car stays held for them for a while.
                        </DialogDescription>
                    </DialogHeader>
                    <TextareaField
                        name="reason"
                        label="Reason shown to the renter"
                        required
                        rows={3}
                        placeholder="We could not find a payment with this code."
                        value={form.data.reason}
                        error={form.errors.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Reject payment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
