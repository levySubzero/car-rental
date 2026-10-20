import { Head, Link, router } from '@inertiajs/react';
import { Mail, MessageCircle, Phone } from 'lucide-react';
import CarController from '@/actions/App/Http/Controllers/Admin/CarController';
import ContactMessageController from '@/actions/App/Http/Controllers/Admin/ContactMessageController';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { ContactStatusBadge } from '@/components/admin/content/contact-status-badge';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import type { AdminContactMessage, Option } from '@/types';

export default function ContactMessageShow({
    message,
    statusOptions,
}: {
    message: AdminContactMessage;
    statusOptions: Option[];
}) {
    const can = useCan();
    const whatsapp = message.phone?.replace(/[^0-9]/g, '');

    return (
        <>
            <Head title={`Message from ${message.name}`} />
            <AdminPage>
                <PageHeader
                    title={message.name}
                    description={`Received ${formatDateTime(message.created_at)}`}
                    actions={
                        can('contact-messages.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title="Delete this message?"
                                description="This cannot be undone."
                                confirmLabel="Delete message"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        ContactMessageController.destroy.url(
                                            message.id,
                                        ),
                                    )
                                }
                            />
                        )
                    }
                />

                <div className="grid max-w-4xl gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Message</CardTitle>
                            {(message.car || message.preferred_dates) && (
                                <CardDescription>
                                    {message.car && (
                                        <>
                                            About{' '}
                                            {can('cars.view') ? (
                                                <Link
                                                    href={CarController.edit(
                                                        message.car.slug,
                                                    )}
                                                    className="underline underline-offset-4"
                                                >
                                                    {message.car.name}
                                                </Link>
                                            ) : (
                                                message.car.name
                                            )}
                                        </>
                                    )}
                                    {message.car &&
                                        message.preferred_dates &&
                                        ' · '}
                                    {message.preferred_dates &&
                                        `Dates: ${message.preferred_dates}`}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-line">
                                {message.message}
                            </p>
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Reply</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-2">
                                {message.email && (
                                    <Button variant="outline" asChild>
                                        <a href={`mailto:${message.email}`}>
                                            <Mail /> {message.email}
                                        </a>
                                    </Button>
                                )}
                                {message.phone && (
                                    <Button variant="outline" asChild>
                                        <a href={`tel:${message.phone}`}>
                                            <Phone /> {message.phone}
                                        </a>
                                    </Button>
                                )}
                                {whatsapp && (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={`https://wa.me/${whatsapp}`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <MessageCircle /> WhatsApp
                                        </a>
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    Status{' '}
                                    <ContactStatusBadge message={message} />
                                </CardTitle>
                                {message.handled_by && (
                                    <CardDescription>
                                        Last changed by {message.handled_by},{' '}
                                        {formatDateTime(message.handled_at)}
                                    </CardDescription>
                                )}
                            </CardHeader>
                            {can('contact-messages.update') && (
                                <CardContent className="flex flex-wrap gap-2">
                                    {statusOptions
                                        .filter(
                                            (option) =>
                                                option.value !== message.status,
                                        )
                                        .map((option) => (
                                            <Button
                                                key={option.value}
                                                variant="secondary"
                                                size="sm"
                                                onClick={() =>
                                                    router.put(
                                                        ContactMessageController.update.url(
                                                            message.id,
                                                        ),
                                                        {
                                                            status: option.value,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Mark{' '}
                                                {option.label.toLowerCase()}
                                            </Button>
                                        ))}
                                </CardContent>
                            )}
                        </Card>
                    </div>
                </div>
            </AdminPage>
        </>
    );
}

ContactMessageShow.layout = {
    breadcrumbs: [
        { title: 'Messages', href: ContactMessageController.index() },
    ],
};
