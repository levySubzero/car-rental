import { Link, router, useForm } from '@inertiajs/react';
import { CalendarOff, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AvailabilityController from '@/actions/App/Http/Controllers/Admin/AvailabilityController';
import CarBlackoutController from '@/actions/App/Http/Controllers/Admin/CarBlackoutController';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import {
    SelectField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { Badge } from '@/components/ui/badge';
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
import { formatDateTime } from '@/lib/format';
import type { AdminCarBlackout, Option } from '@/types';

/**
 * Periods the car is off the road (maintenance, private use), on the car edit page.
 * Times are entered in the browser's time zone and sent as ISO instants.
 */
export function CarBlackouts({
    carId,
    carSlug,
    blackouts,
    reasons,
    editable,
}: {
    carId: number;
    carSlug: string;
    blackouts: AdminCarBlackout[];
    reasons: Option[];
    editable: boolean;
}) {
    const upcoming = blackouts
        .filter((blackout) => new Date(blackout.ends_at) > new Date())
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
    const past = blackouts.length - upcoming.length;

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    The car cannot be booked during these periods.
                    {past > 0 && ` ${past} past period(s) are hidden.`}{' '}
                    <Link
                        href={AvailabilityController.index.url({
                            query: { car: carId },
                        })}
                        className="underline"
                    >
                        See bookings on the calendar
                    </Link>
                </p>
                {editable && (
                    <NewBlackout carSlug={carSlug} reasons={reasons} />
                )}
            </div>

            {upcoming.length === 0 ? (
                <div className="flex items-center gap-2 rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                    <CalendarOff className="size-4" /> No upcoming blackouts.
                </div>
            ) : (
                <ul className="divide-y rounded-md border">
                    {upcoming.map((blackout) => (
                        <li
                            key={blackout.id}
                            className="flex items-start gap-3 p-3"
                        >
                            <div className="grid min-w-0 flex-1 gap-1">
                                <div className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                    {formatDateTime(blackout.starts_at)} →{' '}
                                    {formatDateTime(blackout.ends_at)}
                                    <Badge variant="secondary">
                                        {blackout.reason_label}
                                    </Badge>
                                </div>
                                {blackout.notes && (
                                    <p className="text-sm text-muted-foreground">
                                        {blackout.notes}
                                    </p>
                                )}
                                {blackout.created_by && (
                                    <p className="text-xs text-muted-foreground">
                                        Added by {blackout.created_by}
                                    </p>
                                )}
                            </div>
                            {editable && (
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Remove blackout"
                                        >
                                            <Trash2 />
                                        </Button>
                                    }
                                    title="Remove this blackout?"
                                    description="The car becomes bookable for this period again."
                                    confirmLabel="Remove"
                                    destructive
                                    onConfirm={() =>
                                        router.delete(
                                            CarBlackoutController.destroy.url({
                                                car: carSlug,
                                                blackout: blackout.id,
                                            }),
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function NewBlackout({
    carSlug,
    reasons,
}: {
    carSlug: string;
    reasons: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        starts_at: '',
        ends_at: '',
        reason: reasons[0]?.value ?? 'maintenance',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            starts_at: toInstant(data.starts_at),
            ends_at: toInstant(data.ends_at),
        }));

        form.post(CarBlackoutController.store.url(carSlug), {
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
                <Button variant="outline" size="sm">
                    <Plus /> Add blackout
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Add a blackout</DialogTitle>
                        <DialogDescription>
                            Take the car off the road for maintenance or private
                            use.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            name="starts_at"
                            label="From"
                            type="datetime-local"
                            required
                            value={form.data.starts_at}
                            error={form.errors.starts_at}
                            onChange={(event) =>
                                form.setData('starts_at', event.target.value)
                            }
                        />
                        <TextField
                            name="ends_at"
                            label="Until"
                            type="datetime-local"
                            required
                            min={form.data.starts_at || undefined}
                            value={form.data.ends_at}
                            error={form.errors.ends_at}
                            onChange={(event) =>
                                form.setData('ends_at', event.target.value)
                            }
                        />
                    </div>
                    <SelectField
                        name="reason"
                        label="Reason"
                        options={reasons}
                        value={form.data.reason}
                        error={form.errors.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
                    <TextareaField
                        name="notes"
                        label="Notes"
                        rows={2}
                        maxLength={500}
                        value={form.data.notes}
                        error={form.errors.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Add blackout
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// "2026-10-20T08:00" in the browser's zone → an ISO instant the server stores as is.
function toInstant(local: string): string {
    return local ? new Date(local).toISOString() : '';
}
