import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    LayoutGrid,
    List,
    Search,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/admin/form-field';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useViewMode } from '@/hooks/use-view-mode';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export type Column<T> = {
    key: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    // Query value sent as ?sort= when the header is clicked. Omit for unsortable columns.
    sortKey?: string;
    className?: string;
};

type Filters = Record<string, string | number | null | undefined>;

/**
 * Applies new query parameters to the current page, keeping the other filters
 * and resetting pagination.
 */
export function useTableQuery(filters: Filters) {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (changes: Filters) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) =>
                    value !== null && value !== undefined && value !== '',
            ),
        );

        router.get(path, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
}

/**
 * Server-driven table: sorting, filtering and pagination all round-trip through
 * the query string, so the server stays the source of truth.
 *
 * Pass `grid` (a card per row) and `viewKey` to offer a grid view as well. Phones
 * start on the grid; the choice is remembered per `viewKey`.
 */
export function DataTable<T>({
    data,
    columns,
    filters,
    rowKey,
    searchPlaceholder,
    toolbar,
    empty = 'Nothing to show yet.',
    grid,
    viewKey,
}: {
    data: Paginated<T>;
    columns: Column<T>[];
    filters: Filters;
    rowKey: (row: T) => string | number;
    // Shows a search box bound to ?search= when set.
    searchPlaceholder?: string;
    // Extra filter controls shown next to the search box.
    toolbar?: ReactNode;
    empty?: ReactNode;
    // Renders one row as a card for the grid view.
    grid?: (row: T) => ReactNode;
    // Remembers the chosen view, e.g. "cars".
    viewKey?: string;
}) {
    const apply = useTableQuery(filters);
    const [storedView, setView] = useViewMode(viewKey ?? 'default');
    const view = grid ? storedView : 'table';
    const sort = filters.sort as string | undefined;
    const direction = filters.direction === 'desc' ? 'desc' : 'asc';

    const toggleSort = (key: string) =>
        apply({
            sort: key,
            direction: sort === key && direction === 'asc' ? 'desc' : 'asc',
        });

    return (
        <div className="space-y-4">
            {(searchPlaceholder || toolbar || grid) && (
                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    {searchPlaceholder && (
                        <SearchInput
                            initial={(filters.search as string) ?? ''}
                            placeholder={searchPlaceholder}
                            onSearch={(search) => apply({ search })}
                        />
                    )}
                    {toolbar}
                    {grid && (
                        <div className="flex items-center gap-2 sm:ml-auto">
                            {view === 'grid' && (
                                <GridSort
                                    columns={columns}
                                    sort={sort}
                                    direction={direction}
                                    onChange={(changes) => apply(changes)}
                                />
                            )}
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={view}
                                onValueChange={(value) =>
                                    value && setView(value as 'table' | 'grid')
                                }
                                aria-label="View"
                                className="shrink-0"
                            >
                                <ToggleGroupItem
                                    value="table"
                                    aria-label="Table view"
                                >
                                    <List />
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="grid"
                                    aria-label="Grid view"
                                >
                                    <LayoutGrid />
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>
                    )}
                </div>
            )}

            {view === 'grid' && grid ? (
                data.data.length === 0 ? (
                    <div className="flex h-24 items-center justify-center rounded-lg border text-sm text-muted-foreground">
                        {empty}
                    </div>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {data.data.map((row) => (
                            <li key={rowKey(row)} className="min-w-0">
                                {grid(row)}
                            </li>
                        ))}
                    </ul>
                )
            ) : (
                <div className="overflow-hidden rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        className={column.className}
                                    >
                                        {column.sortKey ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1 hover:text-foreground"
                                                onClick={() =>
                                                    toggleSort(column.sortKey!)
                                                }
                                            >
                                                {column.header}
                                                <SortIcon
                                                    active={
                                                        sort === column.sortKey
                                                    }
                                                    direction={direction}
                                                />
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {empty}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                data.data.map((row) => (
                                    <TableRow key={rowKey(row)}>
                                        {columns.map((column) => (
                                            <TableCell
                                                key={column.key}
                                                className={column.className}
                                            >
                                                {column.cell(row)}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            )}

            <DataTablePagination meta={data.meta} />
        </div>
    );
}

/**
 * Sorting for the grid view, which has no column headers to click.
 */
function GridSort<T>({
    columns,
    sort,
    direction,
    onChange,
}: {
    columns: Column<T>[];
    sort: string | undefined;
    direction: 'asc' | 'desc';
    onChange: (changes: Filters) => void;
}) {
    const options = columns
        .filter((column) => column.sortKey)
        .flatMap((column) => {
            const label =
                typeof column.header === 'string' ? column.header : column.key;

            return [
                { value: `${column.sortKey}:asc`, label: `${label} ↑` },
                { value: `${column.sortKey}:desc`, label: `${label} ↓` },
            ];
        });

    if (options.length === 0) {
        return null;
    }

    return (
        <NativeSelect
            aria-label="Sort by"
            className="w-full sm:w-44"
            value={sort ? `${sort}:${direction}` : ''}
            placeholder="Default order"
            options={options}
            onChange={(event) => {
                const [key, dir] = event.target.value.split(':');

                onChange({ sort: key || null, direction: dir || null });
            }}
        />
    );
}

function SortIcon({
    active,
    direction,
}: {
    active: boolean;
    direction: 'asc' | 'desc';
}) {
    if (!active) {
        return <ArrowUpDown className="size-3.5 opacity-40" />;
    }

    return direction === 'asc' ? (
        <ArrowUp className="size-3.5" />
    ) : (
        <ArrowDown className="size-3.5" />
    );
}

function SearchInput({
    initial,
    placeholder,
    onSearch,
}: {
    initial: string;
    placeholder: string;
    onSearch: (value: string) => void;
}) {
    const [value, setValue] = useState(initial);
    const first = useRef(true);
    const onSearchRef = useRef(onSearch);
    onSearchRef.current = onSearch;

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => onSearchRef.current(value), 300);

        return () => clearTimeout(timer);
    }, [value]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                type="search"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                placeholder={placeholder}
                className="pl-8"
                aria-label={placeholder}
            />
        </div>
    );
}

export function DataTablePagination({
    meta,
}: {
    meta: Paginated<unknown>['meta'];
}) {
    if (meta.last_page <= 1) {
        return (
            <p className="text-sm text-muted-foreground">
                {meta.total} {meta.total === 1 ? 'result' : 'results'}
            </p>
        );
    }

    return (
        <div className="flex flex-col items-center justify-between gap-2 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing {meta.from}–{meta.to} of {meta.total}
            </p>
            <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                {meta.links.map((link, index) => (
                    <Button
                        key={index}
                        asChild={link.url !== null}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        disabled={link.url === null}
                        className={cn(link.url === null && 'opacity-50')}
                    >
                        {link.url !== null ? (
                            <Link href={link.url} preserveScroll preserveState>
                                {decodeLabel(link.label)}
                            </Link>
                        ) : (
                            <span>{decodeLabel(link.label)}</span>
                        )}
                    </Button>
                ))}
            </nav>
        </div>
    );
}

// Laravel's paginator labels contain HTML entities for the arrows.
function decodeLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}
