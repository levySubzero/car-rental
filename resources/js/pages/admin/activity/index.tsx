import { Head } from '@inertiajs/react';
import { useState } from 'react';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTime, humanize } from '@/lib/format';
import type { ActivityEntry, Paginated } from '@/types';

type Filters = {
    log?: string;
    event?: string;
    causer?: string;
    subject_type?: string;
    from?: string;
    to?: string;
};

export default function ActivityIndex({
    activities,
    filters,
    options,
}: {
    activities: Paginated<ActivityEntry>;
    filters: Filters;
    options: { logs: string[]; events: string[]; subject_types: string[] };
}) {
    const apply = useTableQuery(filters);
    const [selected, setSelected] = useState<ActivityEntry | null>(null);

    const columns: Column<ActivityEntry>[] = [
        {
            key: 'when',
            header: 'When',
            className: 'whitespace-nowrap',
            cell: (entry) => formatDateTime(entry.created_at),
        },
        {
            key: 'who',
            header: 'Who',
            cell: (entry) => entry.causer?.label ?? 'System',
        },
        {
            key: 'what',
            header: 'What',
            cell: (entry) => (
                <div className="flex flex-wrap items-center gap-2">
                    {entry.log && (
                        <Badge variant="secondary">{entry.log}</Badge>
                    )}
                    <span>{entry.description}</span>
                </div>
            ),
        },
        {
            key: 'subject',
            header: 'Record',
            className: 'hidden md:table-cell',
            cell: (entry) =>
                entry.subject
                    ? `${entry.subject.type}: ${entry.subject.label ?? entry.subject.id}`
                    : '—',
        },
        {
            key: 'details',
            header: <span className="sr-only">Details</span>,
            className: 'text-right',
            cell: (entry) => (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setSelected(entry)}
                >
                    Details
                </Button>
            ),
        },
    ];

    const toOptions = (values: string[], label = humanize) =>
        values.map((value) => ({ value, label: label(value) }));

    return (
        <>
            <Head title="Activity log" />
            <AdminPage>
                <PageHeader
                    title="Activity log"
                    description="Who changed what, and when. Document views and booking status changes are recorded here too."
                />
                <DataTable
                    data={activities}
                    columns={columns}
                    filters={filters}
                    rowKey={(entry) => entry.id}
                    empty="No activity matches these filters."
                    toolbar={
                        <div className="flex flex-wrap gap-2">
                            <NativeSelect
                                aria-label="Area"
                                className="w-40"
                                value={filters.log ?? ''}
                                placeholder="All areas"
                                options={toOptions(options.logs)}
                                onChange={(event) =>
                                    apply({ log: event.target.value })
                                }
                            />
                            <NativeSelect
                                aria-label="Event"
                                className="w-40"
                                value={filters.event ?? ''}
                                placeholder="All events"
                                options={toOptions(options.events)}
                                onChange={(event) =>
                                    apply({ event: event.target.value })
                                }
                            />
                            <NativeSelect
                                aria-label="Record type"
                                className="w-44"
                                value={filters.subject_type ?? ''}
                                placeholder="All records"
                                options={toOptions(
                                    options.subject_types,
                                    (type) => type.split('\\').pop() ?? type,
                                )}
                                onChange={(event) =>
                                    apply({ subject_type: event.target.value })
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

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    {selected && <ActivityDetails entry={selected} />}
                </SheetContent>
            </Sheet>
        </>
    );
}

function ActivityDetails({ entry }: { entry: ActivityEntry }) {
    const after = entry.changes.attributes ?? {};
    const before = entry.changes.old ?? {};
    const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])];

    return (
        <>
            <SheetHeader>
                <SheetTitle>{entry.description}</SheetTitle>
                <SheetDescription>
                    {entry.causer?.label ?? 'System'} ·{' '}
                    {formatDateTime(entry.created_at)}
                </SheetDescription>
            </SheetHeader>
            <div className="grid gap-6 px-4 pb-6 text-sm">
                {keys.length > 0 && (
                    <table className="w-full table-fixed border-collapse">
                        <thead>
                            <tr className="text-left text-xs text-muted-foreground">
                                <th className="w-1/4 pb-2 font-medium">
                                    Field
                                </th>
                                <th className="pb-2 font-medium">Before</th>
                                <th className="pb-2 font-medium">After</th>
                            </tr>
                        </thead>
                        <tbody>
                            {keys.map((key) => (
                                <tr key={key} className="border-t align-top">
                                    <td className="py-2 pr-2 font-medium break-words">
                                        {humanize(key)}
                                    </td>
                                    <td className="py-2 pr-2 break-words text-muted-foreground">
                                        <Value value={before[key]} />
                                    </td>
                                    <td className="py-2 break-words">
                                        <Value value={after[key]} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {Object.keys(entry.properties).length > 0 && (
                    <div className="grid gap-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            Details
                        </p>
                        <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs">
                            {JSON.stringify(entry.properties, null, 2)}
                        </pre>
                    </div>
                )}
            </div>
        </>
    );
}

function Value({ value }: { value: unknown }) {
    if (value === null || value === undefined || value === '') {
        return <span>—</span>;
    }

    if (typeof value === 'boolean') {
        return <span>{value ? 'Yes' : 'No'}</span>;
    }

    if (typeof value === 'string' || typeof value === 'number') {
        return <span>{value}</span>;
    }

    return (
        <pre className="text-xs whitespace-pre-wrap">
            {JSON.stringify(value, null, 2)}
        </pre>
    );
}

ActivityIndex.layout = {
    breadcrumbs: [
        { title: 'Activity log', href: ActivityLogController.index() },
    ],
};
