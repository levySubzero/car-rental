import { Badge } from '@/components/ui/badge';
import type { BookingStatus } from '@/types';

// Filled while staff have something to do; outlined once the booking has ended.
const VARIANTS: Record<
    BookingStatus,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    draft: 'outline',
    awaiting_payment: 'secondary',
    payment_submitted: 'default',
    awaiting_cash: 'default',
    payment_rejected: 'destructive',
    reserved: 'default',
    active: 'secondary',
    completed: 'outline',
    cancelled: 'outline',
    no_show: 'outline',
    expired: 'outline',
};

export function BookingStatusBadge({
    booking,
}: {
    booking: { status: BookingStatus; status_label: string };
}) {
    return (
        <Badge variant={VARIANTS[booking.status]}>{booking.status_label}</Badge>
    );
}
