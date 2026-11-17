import { Head, Link } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import MailLogController from '@/actions/App/Http/Controllers/Admin/MailLogController';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import type { Option, Paginated } from '@/types';

type LoggedEmail = {
    id: number;
    to: string;
    subject: string | null;
    type: string | null;
    mailer: string | null;
    attachments: number;
    created_at: string;
};

type Filters = {
    search?: string;
    notification?: string;
    from?: string;
    to?: string;
};

/**
 * Every email the app sent, newest first. Under the `log` mailer (dev) nothing really
 * leaves, so this is where to read what would have gone out.
 */
export default function MailLogIndex({
    emails,
    filters,
    types,
}: {
    emails: Paginated<LoggedEmail>;
    filters: Filters;
    types: Option[];
}) {
    const apply = useTableQuery(filters);

    const columns: Column<LoggedEmail>[] = [
        {
            key: 'when',
            header: 'Sent',
            className: 'whitespace-nowrap',
            cell: (email) => formatDateTime(email.created_at),
        },
        {
            key: 'to',
            header: 'To',
            className: 'max-w-56 truncate',
            cell: (email) => email.to,
        },
        {
            key: 'subject',
            header: 'Subject',
            cell: (email) => (
                <Link
                    href={MailLogController.show.url(email.id)}
                    className="flex items-center gap-2 font-medium hover:underline"
                >
                    <span>{email.subject ?? '(no subject)'}</span>
                    {email.attachments > 0 && (
                        <Paperclip
                            className="size-3.5 shrink-0 text-muted-foreground"
                            aria-label={`${email.attachments} attachment(s)`}
                        />
                    )}
                </Link>
            ),
        },
        {
            key: 'type',
            header: 'Type',
            className: 'hidden md:table-cell',
            cell: (email) => (
                <div className="flex flex-wrap gap-1">
                    {email.type && (
                        <Badge variant="secondary">{email.type}</Badge>
                    )}
                    {email.mailer === 'log' && (
                        <Badge variant="outline">not delivered (log)</Badge>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Email log" />
            <AdminPage>
                <PageHeader
                    title="Email log"
                    description="A copy of every email the app sent. When the mailer is set to log (development), nothing is delivered and this is where to read it."
                />
                <DataTable
                    data={emails}
                    columns={columns}
                    filters={filters}
                    rowKey={(email) => email.id}
                    empty="No emails match these filters."
                    toolbar={
                        <div className="flex flex-wrap gap-2">
                            <Input
                                type="search"
                                aria-label="Search"
                                placeholder="Recipient or subject"
                                className="w-56"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        apply({
                                            search: event.currentTarget.value,
                                        });
                                    }
                                }}
                            />
                            <NativeSelect
                                aria-label="Type"
                                className="w-48"
                                value={filters.notification ?? ''}
                                placeholder="All types"
                                options={types}
                                onChange={(event) =>
                                    apply({ notification: event.target.value })
                                }
                            />
                            <Input
                                type="date"
                                aria-label="From"
                                className="w-40"
                                value={filters.from ?? ''}
                                onChange={(event) =>
                                    apply({ from: event.target.value })
                                }
                            />
                            <Input
                                type="date"
                                aria-label="To"
                                className="w-40"
                                value={filters.to ?? ''}
                                onChange={(event) =>
                                    apply({ to: event.target.value })
                                }
                            />
                        </div>
                    }
                />
            </AdminPage>
        </>
    );
}

MailLogIndex.layout = {
    breadcrumbs: [{ title: 'Email log', href: MailLogController.index() }],
};
