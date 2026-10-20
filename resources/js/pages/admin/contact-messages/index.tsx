import { Head, Link } from '@inertiajs/react';
import ContactMessageController from '@/actions/App/Http/Controllers/Admin/ContactMessageController';
import { ContactStatusBadge } from '@/components/admin/content/contact-status-badge';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { formatDateTime } from '@/lib/format';
import type { AdminContactMessage, Option, Paginated } from '@/types';

type Filters = { search?: string; status?: string };

export default function ContactMessagesIndex({
    messages,
    filters,
    counts,
    statusOptions,
}: {
    messages: Paginated<AdminContactMessage>;
    filters: Filters;
    counts: Record<string, number>;
    statusOptions: Option[];
}) {
    const apply = useTableQuery(filters);

    const columns: Column<AdminContactMessage>[] = [
        {
            key: 'name',
            header: 'From',
            cell: (message) => (
                <div className="grid">
                    <Link
                        href={ContactMessageController.show(message.id)}
                        className={
                            message.status === 'new'
                                ? 'font-semibold hover:underline'
                                : 'hover:underline'
                        }
                    >
                        {message.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">
                        {message.email ?? message.phone}
                    </span>
                </div>
            ),
        },
        {
            key: 'message',
            header: 'Message',
            className: 'hidden md:table-cell max-w-md',
            cell: (message) => (
                <span className="line-clamp-2 text-sm text-muted-foreground">
                    {message.car && (
                        <span className="text-foreground">
                            {message.car.name} ·{' '}
                        </span>
                    )}
                    {message.message}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (message) => <ContactStatusBadge message={message} />,
        },
        {
            key: 'created_at',
            header: 'Received',
            className: 'hidden sm:table-cell whitespace-nowrap',
            cell: (message) => formatDateTime(message.created_at),
        },
    ];

    return (
        <>
            <Head title="Messages" />
            <AdminPage>
                <PageHeader
                    title="Messages"
                    description={`Enquiries from the website contact form. ${counts.new ?? 0} new.`}
                />
                <DataTable
                    data={messages}
                    columns={columns}
                    filters={filters}
                    rowKey={(message) => message.id}
                    searchPlaceholder="Search name, email or phone"
                    empty="No messages."
                    toolbar={
                        <NativeSelect
                            aria-label="Status"
                            className="sm:w-48"
                            value={filters.status ?? ''}
                            placeholder="All but spam"
                            options={statusOptions.map((option) => ({
                                ...option,
                                label: `${option.label} (${counts[option.value] ?? 0})`,
                            }))}
                            onChange={(event) =>
                                apply({ status: event.target.value })
                            }
                        />
                    }
                />
            </AdminPage>
        </>
    );
}

ContactMessagesIndex.layout = {
    breadcrumbs: [
        { title: 'Messages', href: ContactMessageController.index() },
    ],
};
