import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import FxRateController from '@/actions/App/Http/Controllers/Admin/FxRateController';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { SelectField, TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import type { AdminFxRate, FxRefreshOutcome, ResolvedFxRate } from '@/types';

type Provider = {
    key: string;
    label: string;
    has_key: boolean;
    stale_after_hours: number;
    max_jump_percent: number;
};

// "129.250000" → "129.25"
function rate(value: string): string {
    return value.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

export default function FxRatesIndex({
    base,
    currencies,
    rates,
    history,
    provider,
    lastRefresh,
}: {
    base: string;
    currencies: string[];
    rates: ResolvedFxRate[];
    history: AdminFxRate[];
    provider: Provider;
    lastRefresh: FxRefreshOutcome | null;
}) {
    const can = useCan();
    const editable = can('fx-rates.override');
    const isApi = provider.key !== 'manual';
    const missing = currencies.filter(
        (code) => !rates.some((resolved) => resolved.code === code),
    );
    const rejected = Object.entries(lastRefresh?.rejected ?? {});

    const refresh = (acceptJumps: boolean) =>
        router.post(
            FxRateController.refresh.url(),
            { accept_jumps: acceptJumps },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Exchange rates" />
            <AdminPage>
                <PageHeader
                    title="Exchange rates"
                    description={`${base} per unit of each currency. Display prices use the market rate minus the margin; foreign charges such as the chauffeur use the market rate.`}
                    actions={
                        editable && (
                            <div className="flex flex-wrap gap-2">
                                {isApi && (
                                    <Button
                                        variant="outline"
                                        onClick={() => refresh(false)}
                                    >
                                        <RefreshCw /> Refresh now
                                    </Button>
                                )}
                                <ManualRateDialog currencies={currencies} />
                            </div>
                        )
                    }
                />

                <div className="grid max-w-5xl gap-6">
                    {lastRefresh?.status === 'failed' && (
                        <Alert variant="destructive">
                            <AlertTriangle />
                            <AlertTitle>The last refresh failed</AlertTitle>
                            <AlertDescription>
                                {formatDateTime(lastRefresh.at)}:{' '}
                                {lastRefresh.message}
                            </AlertDescription>
                        </Alert>
                    )}

                    {rejected.length > 0 && (
                        <Alert>
                            <AlertTriangle />
                            <AlertTitle>Rates held back for review</AlertTitle>
                            <AlertDescription className="grid gap-2">
                                <p>
                                    These moved more than{' '}
                                    {provider.max_jump_percent}% since the last
                                    rate, so the old rate is still in use:
                                </p>
                                <ul className="list-inside list-disc">
                                    {rejected.map(([code, jump]) => (
                                        <li key={code}>
                                            {code}: {rate(jump.previous)} →{' '}
                                            {rate(jump.fetched)} ({jump.change}
                                            %)
                                        </li>
                                    ))}
                                </ul>
                                {editable && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="w-fit"
                                            >
                                                Accept the new rates
                                            </Button>
                                        }
                                        title="Accept the new rates?"
                                        description="Fetch again and store the rates even though they moved sharply. Display prices change at once."
                                        confirmLabel="Fetch and accept"
                                        onConfirm={() => refresh(true)}
                                    />
                                )}
                            </AlertDescription>
                        </Alert>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Rates in use</CardTitle>
                            <CardDescription>
                                Source: {provider.label}
                                {isApi && !provider.has_key && (
                                    <span className="text-destructive">
                                        {' '}
                                        — FX_API_KEY is not set, so nothing is
                                        fetched.
                                    </span>
                                )}
                                . A fresh API rate always wins; a manual rate
                                applies only when the API rate is missing or
                                older than {provider.stale_after_hours} hours.
                                {lastRefresh &&
                                    ` Last attempt ${formatDateTime(lastRefresh.at)}.`}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Currency</TableHead>
                                        <TableHead className="text-right">
                                            Market
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Display
                                        </TableHead>
                                        <TableHead>Source</TableHead>
                                        <TableHead>As of</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rates.map((resolved) => (
                                        <TableRow key={resolved.code}>
                                            <TableCell className="font-mono font-medium">
                                                {resolved.code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {rate(resolved.market)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {rate(resolved.effective)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    <Badge variant="secondary">
                                                        {resolved.source ===
                                                        'api'
                                                            ? 'API'
                                                            : 'Manual'}
                                                    </Badge>
                                                    {resolved.stale && (
                                                        <Badge variant="destructive">
                                                            Stale
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDateTime(
                                                    resolved.fetched_at,
                                                )}
                                                {resolved.override_until &&
                                                    ` · until ${formatDateTime(resolved.override_until)}`}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {missing.map((code) => (
                                        <TableRow key={code}>
                                            <TableCell className="font-mono font-medium">
                                                {code}
                                            </TableCell>
                                            <TableCell
                                                colSpan={4}
                                                className="text-sm text-destructive"
                                            >
                                                No rate: prices are not shown in{' '}
                                                {code}.
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>History</CardTitle>
                            <CardDescription>
                                Every fetched and manual rate, newest first.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {history.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No rates yet.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Currency</TableHead>
                                            <TableHead className="text-right">
                                                Market
                                            </TableHead>
                                            <TableHead>Source</TableHead>
                                            <TableHead>As of</TableHead>
                                            <TableHead />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {history.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell className="font-mono">
                                                    {row.currency_code}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {rate(row.market_rate)}
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    {row.source_label}
                                                    {row.override_by &&
                                                        ` by ${row.override_by}`}
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {formatDateTime(
                                                        row.fetched_at,
                                                    )}
                                                    {row.override_until &&
                                                        ` · until ${formatDateTime(row.override_until)}`}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {editable &&
                                                        row.is_live_override && (
                                                            <ConfirmDialog
                                                                trigger={
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                    >
                                                                        End
                                                                    </Button>
                                                                }
                                                                title={`End the manual ${row.currency_code} rate?`}
                                                                description="It stops applying now. The row stays in the history."
                                                                confirmLabel="End rate"
                                                                onConfirm={() =>
                                                                    router.put(
                                                                        FxRateController.expire.url(
                                                                            row.id,
                                                                        ),
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </AdminPage>
        </>
    );
}

function ManualRateDialog({ currencies }: { currencies: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        currency_code: currencies[0] ?? '',
        market_rate: '',
        override_until: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            override_until: data.override_until
                ? new Date(data.override_until).toISOString()
                : null,
        }));

        form.post(FxRateController.store.url(), {
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
                <Button disabled={currencies.length === 0}>
                    <Plus /> Set manual rate
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Set a manual rate</DialogTitle>
                        <DialogDescription>
                            Used only while there is no fresh API rate, e.g.
                            before the API key is set or while the API is down.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            name="currency_code"
                            label="Currency"
                            options={currencies.map((code) => ({
                                value: code,
                                label: code,
                            }))}
                            value={form.data.currency_code}
                            error={form.errors.currency_code}
                            onChange={(event) =>
                                form.setData(
                                    'currency_code',
                                    event.target.value,
                                )
                            }
                        />
                        <TextField
                            name="market_rate"
                            label="Market rate (KES per unit)"
                            inputMode="decimal"
                            required
                            placeholder="129.25"
                            value={form.data.market_rate}
                            error={form.errors.market_rate}
                            onChange={(event) =>
                                form.setData('market_rate', event.target.value)
                            }
                        />
                    </div>
                    <TextField
                        name="override_until"
                        label="Until (optional)"
                        type="datetime-local"
                        value={form.data.override_until}
                        error={form.errors.override_until}
                        description="Leave empty to keep it as the fallback until you end it."
                        onChange={(event) =>
                            form.setData('override_until', event.target.value)
                        }
                    />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save rate
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

FxRatesIndex.layout = {
    breadcrumbs: [{ title: 'Exchange rates', href: FxRateController.index() }],
};
