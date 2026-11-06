import type { ComponentProps } from 'react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import BookingsIndex from '@/pages/admin/bookings/index';

/**
 * Website requests and bookings waiting for payment: the desk over the Requests tabs.
 */
export default function BookingRequests(
    props: Omit<ComponentProps<typeof BookingsIndex>, 'requests'>,
) {
    return <BookingsIndex {...props} requests />;
}

BookingRequests.layout = {
    breadcrumbs: [{ title: 'Requests', href: BookingController.requests() }],
};
