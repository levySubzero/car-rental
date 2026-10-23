import { useForm } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { TextareaField, TextField } from '@/components/admin/form-field';
import { StringListField } from '@/components/admin/list-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';

export type NotificationSettings = {
    staff_emails: string[];
    staff_whatsapp_number: string | null;
    channels: Record<string, { mail: boolean; whatsapp: boolean }>;
    voucher_email_subject: string;
    voucher_email_intro: string;
    voucher_whatsapp_text: string;
};

const EVENTS: Record<string, { label: string; audience: string }> = {
    booking_received: { label: 'Booking received', audience: 'Renter' },
    staff_new_booking: { label: 'New booking', audience: 'Staff' },
    payment_submitted: { label: 'Payment submitted', audience: 'Staff' },
    booking_confirmed: {
        label: 'Booking confirmed (voucher)',
        audience: 'Renter',
    },
    payment_rejected: { label: 'Payment rejected', audience: 'Renter' },
};

export function NotificationSettingsForm({
    values,
}: {
    values: NotificationSettings;
}) {
    const form = useForm({
        staff_emails: values.staff_emails,
        staff_whatsapp_number: values.staff_whatsapp_number ?? '',
        channels: values.channels,
        voucher_email_subject: values.voucher_email_subject,
        voucher_email_intro: values.voucher_email_intro,
        voucher_whatsapp_text: values.voucher_whatsapp_text,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setChannel = (
        event: string,
        channel: 'mail' | 'whatsapp',
        on: boolean,
    ) =>
        form.setData('channels', {
            ...form.data.channels,
            [event]: { ...form.data.channels[event], [channel]: on },
        });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(SettingsController.update.url('notification'), {
                    preserveScroll: true,
                });
            }}
            className="grid gap-6"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Staff recipients</CardTitle>
                    <CardDescription>
                        Who hears about new bookings and payments.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <StringListField
                        name="staff_emails"
                        label="Alert emails"
                        type="email"
                        value={form.data.staff_emails}
                        onChange={(emails) =>
                            form.setData('staff_emails', emails)
                        }
                        errors={errors}
                        addLabel="Add email"
                    />
                    <TextField
                        name="staff_whatsapp_number"
                        label="Staff WhatsApp number"
                        placeholder="+254 7XX XXX XXX"
                        className="max-w-sm"
                        value={form.data.staff_whatsapp_number}
                        error={errors.staff_whatsapp_number}
                        onChange={(event) =>
                            form.setData(
                                'staff_whatsapp_number',
                                event.target.value,
                            )
                        }
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Channels</CardTitle>
                    <CardDescription>
                        Turn each message on or off per channel. Until the
                        WhatsApp Business API is connected, WhatsApp messages
                        are prepared as links for staff to send.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs text-muted-foreground">
                                <th className="pb-2 font-medium">Event</th>
                                <th className="pb-2 font-medium">To</th>
                                <th className="pb-2 text-center font-medium">
                                    Email
                                </th>
                                <th className="pb-2 text-center font-medium">
                                    WhatsApp
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(EVENTS).map(([key, event]) => (
                                <tr key={key} className="border-t">
                                    <td className="py-2">{event.label}</td>
                                    <td className="py-2 text-muted-foreground">
                                        {event.audience}
                                    </td>
                                    {(['mail', 'whatsapp'] as const).map(
                                        (channel) => (
                                            <td
                                                key={channel}
                                                className="py-2 text-center"
                                            >
                                                <Checkbox
                                                    aria-label={`${event.label} by ${channel === 'mail' ? 'email' : 'WhatsApp'}`}
                                                    checked={
                                                        form.data.channels[
                                                            key
                                                        ]?.[channel] ?? false
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setChannel(
                                                            key,
                                                            channel,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                            </td>
                                        ),
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Voucher email</CardTitle>
                    <CardDescription>
                        Sent when a booking is confirmed. {'{reference}'} is
                        replaced with the booking reference.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <TextField
                        name="voucher_email_subject"
                        label="Subject"
                        required
                        value={form.data.voucher_email_subject}
                        error={errors.voucher_email_subject}
                        onChange={(event) =>
                            form.setData(
                                'voucher_email_subject',
                                event.target.value,
                            )
                        }
                    />
                    <TextareaField
                        name="voucher_email_intro"
                        label="Introduction"
                        rows={4}
                        required
                        value={form.data.voucher_email_intro}
                        error={errors.voucher_email_intro}
                        onChange={(event) =>
                            form.setData(
                                'voucher_email_intro',
                                event.target.value,
                            )
                        }
                    />
                    <TextareaField
                        name="voucher_whatsapp_text"
                        label="WhatsApp message"
                        description="Prefilled when staff send the confirmation on WhatsApp. {reference}, {car} and {dates} are filled in."
                        rows={3}
                        required
                        value={form.data.voucher_whatsapp_text}
                        error={errors.voucher_whatsapp_text}
                        onChange={(event) =>
                            form.setData(
                                'voucher_whatsapp_text',
                                event.target.value,
                            )
                        }
                    />
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save notification settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
