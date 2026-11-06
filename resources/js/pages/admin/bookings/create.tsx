import { Head, useForm } from '@inertiajs/react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import BookingCreateController from '@/actions/App/Http/Controllers/Admin/BookingCreateController';
import {
    MoneyField,
    SelectField,
    TextField,
} from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { fromLocalInput } from '@/lib/booking';
import type { Option } from '@/types';

/**
 * A booking for a client who did not book online (a call or a walk-in). It is confirmed on
 * saving, so it blocks the car; staff then review and send the voucher.
 */
export default function BookingCreate({
    cars,
    zones,
    extras,
    modes,
    methods,
}: {
    cars: Option[];
    zones: Option[];
    extras: Option[];
    modes: Option[];
    methods: Option[];
}) {
    const form = useForm({
        car_id: '',
        first_name: '',
        surname: '',
        email: '',
        phone: '',
        pickup_at: '',
        return_at: '',
        pickup_mode: 'office',
        return_mode: 'office',
        delivery_zone_id: '',
        delivery_address: '',
        delivery_contact_phone: '',
        return_address: '',
        return_contact_phone: '',
        extras: [] as string[],
        total: '',
        deposit: '',
        paid: '',
        payment_method: '',
        payment_reference: '',
    });
    const delivers = form.data.pickup_mode === 'address';
    const collects = form.data.return_mode === 'address';

    const text = (key: Exclude<keyof typeof form.data, 'extras'>) => ({
        name: key,
        value: form.data[key],
        error: form.errors[key],
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
        ) => form.setData(key, event.target.value),
    });

    return (
        <>
            <Head title="Enter a booking" />
            <AdminPage>
                <PageHeader
                    title="Enter a booking"
                    description="For a client who did not book on the website. It is confirmed on saving and blocks the car; you review the voucher next."
                />
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            pickup_at: fromLocalInput(data.pickup_at),
                            return_at: fromLocalInput(data.return_at),
                            // The client's number reaches the driver unless staff give another.
                            delivery_contact_phone:
                                data.delivery_contact_phone || data.phone,
                            return_contact_phone:
                                data.return_contact_phone || data.phone,
                        }));
                        form.post(BookingCreateController.store.url());
                    }}
                    className="grid gap-4 lg:grid-cols-2"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Client</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                label="First name"
                                required
                                {...text('first_name')}
                            />
                            <TextField
                                label="Surname"
                                required
                                {...text('surname')}
                            />
                            <TextField
                                label="Email"
                                type="email"
                                description="Needed to email the voucher."
                                {...text('email')}
                            />
                            <TextField label="Phone" {...text('phone')} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Car and dates</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                label="Car"
                                className="sm:col-span-2"
                                placeholder="Choose a car"
                                options={cars}
                                {...text('car_id')}
                            />
                            <TextField
                                label="Pick-up (Nairobi time)"
                                type="datetime-local"
                                required
                                {...text('pickup_at')}
                            />
                            <TextField
                                label="Return (Nairobi time)"
                                type="datetime-local"
                                required
                                {...text('return_at')}
                            />
                            <SelectField
                                label="Pick-up place"
                                options={modes}
                                {...text('pickup_mode')}
                            />
                            <SelectField
                                label="Return place"
                                options={modes}
                                {...text('return_mode')}
                            />
                            {(delivers || collects) && (
                                <SelectField
                                    label="Delivery zone"
                                    className="sm:col-span-2"
                                    placeholder="Choose a zone"
                                    options={zones}
                                    {...text('delivery_zone_id')}
                                />
                            )}
                            {delivers && (
                                <TextField
                                    label="Delivery address"
                                    className="sm:col-span-2"
                                    {...text('delivery_address')}
                                />
                            )}
                            {collects && (
                                <TextField
                                    label="Collection address"
                                    className="sm:col-span-2"
                                    {...text('return_address')}
                                />
                            )}
                            {extras.map((extra) => (
                                <label
                                    key={extra.value}
                                    className="flex items-center gap-2 text-sm sm:col-span-2"
                                >
                                    <Checkbox
                                        checked={form.data.extras.includes(
                                            extra.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'extras',
                                                checked
                                                    ? [
                                                          ...form.data.extras,
                                                          extra.value,
                                                      ]
                                                    : form.data.extras.filter(
                                                          (code) =>
                                                              code !==
                                                              extra.value,
                                                      ),
                                            )
                                        }
                                    />
                                    {extra.label}
                                </label>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Price and payment</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <MoneyField
                                label="Agreed total"
                                description="Leave empty to use the car's normal rate."
                                {...text('total')}
                            />
                            <MoneyField
                                label="Security deposit"
                                {...text('deposit')}
                            />
                            <MoneyField
                                label="Paid so far"
                                description="Leave empty if nothing is paid yet; the total is then due at handover."
                                {...text('paid')}
                            />
                            <SelectField
                                label="Paid by"
                                placeholder="Choose a method"
                                options={methods}
                                {...text('payment_method')}
                            />
                            <TextField
                                label="Payment reference"
                                {...text('payment_reference')}
                            />
                        </CardContent>
                    </Card>

                    <div className="lg:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Save and review the voucher
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}

BookingCreate.layout = {
    breadcrumbs: [
        { title: 'Bookings', href: BookingController.index() },
        { title: 'Enter a booking', href: BookingCreateController.create() },
    ],
};
