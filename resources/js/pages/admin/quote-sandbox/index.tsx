import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Calculator, Link2 } from 'lucide-react';
import QuoteSandboxController from '@/actions/App/Http/Controllers/Admin/QuoteSandboxController';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { percentFromBp } from '@/lib/format';
import type { AdminQuote, Money, Option } from '@/types';

type Values = {
    car: string;
    pickup_at: string;
    return_at: string;
    extras: string[];
    delivery_zone_id: string;
    driver_dob: string;
};

const nairobiTime = new Intl.DateTimeFormat('en-KE', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Africa/Nairobi',
});

function amount(money: Money | null | undefined): string {
    return money?.display ?? '—';
}

export default function QuoteSandbox({
    values,
    quote,
    error,
    timezone,
    cars,
    extras,
    zones,
}: {
    values: Values;
    quote: AdminQuote | null;
    error: string | null;
    timezone: string;
    cars: Option[];
    extras: Option[];
    zones: Option[];
}) {
    const form = useForm<Values>(values);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(QuoteSandboxController.url(), form.data, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const toggleExtra = (value: string, checked: boolean) =>
        form.setData(
            'extras',
            checked
                ? [...form.data.extras, value]
                : form.data.extras.filter((extra) => extra !== value),
        );

    return (
        <>
            <Head title="Quote sandbox" />
            <AdminPage>
                <PageHeader
                    title="Quote sandbox"
                    description="Price any car with the rules in force now, exactly as a booking would. The link reproduces the scenario."
                    actions={
                        quote && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    navigator.clipboard?.writeText(
                                        window.location.href,
                                    )
                                }
                            >
                                <Link2 /> Copy link
                            </Button>
                        )
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                    <form onSubmit={submit}>
                        <Card>
                            <CardHeader>
                                <CardTitle>Scenario</CardTitle>
                                <CardDescription>
                                    Times are in {timezone} time.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <SelectField
                                    name="car"
                                    label="Car"
                                    options={cars}
                                    placeholder="Choose a car"
                                    required
                                    value={form.data.car}
                                    error={form.errors.car}
                                    onChange={(event) =>
                                        form.setData('car', event.target.value)
                                    }
                                />
                                <TextField
                                    name="pickup_at"
                                    label="Pickup"
                                    type="datetime-local"
                                    required
                                    value={form.data.pickup_at}
                                    error={form.errors.pickup_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'pickup_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <TextField
                                    name="return_at"
                                    label="Return"
                                    type="datetime-local"
                                    required
                                    min={form.data.pickup_at || undefined}
                                    value={form.data.return_at}
                                    error={form.errors.return_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'return_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <fieldset className="grid gap-2">
                                    <legend className="mb-1 text-sm font-medium">
                                        Extras
                                    </legend>
                                    {extras.map((extra) => (
                                        <div
                                            key={extra.value}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`extra-${extra.value}`}
                                                checked={form.data.extras.includes(
                                                    extra.value,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleExtra(
                                                        extra.value,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`extra-${extra.value}`}
                                                className="font-normal"
                                            >
                                                {extra.label}
                                            </Label>
                                        </div>
                                    ))}
                                </fieldset>
                                <SelectField
                                    name="delivery_zone_id"
                                    label="Delivery"
                                    options={zones}
                                    placeholder="Collect from the office"
                                    value={form.data.delivery_zone_id}
                                    error={form.errors.delivery_zone_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'delivery_zone_id',
                                            event.target.value,
                                        )
                                    }
                                />
                                <TextField
                                    name="driver_dob"
                                    label="Driver's date of birth"
                                    type="date"
                                    value={form.data.driver_dob}
                                    error={form.errors.driver_dob}
                                    description="Optional. Needed for the security deposit."
                                    onChange={(event) =>
                                        form.setData(
                                            'driver_dob',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Button type="submit">
                                    <Calculator /> Price it
                                </Button>
                            </CardContent>
                        </Card>
                    </form>

                    <div className="grid content-start gap-6">
                        {error && (
                            <Alert variant="destructive">
                                <AlertTriangle />
                                <AlertTitle>No quote</AlertTitle>
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}
                        {!quote && !error && (
                            <p className="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                Choose a car and both times to see the quote.
                            </p>
                        )}
                        {quote && <QuoteResult quote={quote} />}
                    </div>
                </div>
            </AdminPage>
        </>
    );
}

function QuoteResult({ quote }: { quote: AdminQuote }) {
    const currencies = Object.entries(quote.display);

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>
                        {quote.days} billable day{quote.days === 1 ? '' : 's'}
                    </CardTitle>
                    <CardDescription>
                        {nairobiTime.format(new Date(quote.pickup_at))} →{' '}
                        {nairobiTime.format(new Date(quote.return_at))} ·{' '}
                        {String(quote.settings.day_counting).replace(/_/g, ' ')}
                        {' · '}
                        {quote.mileage_km_per_day === null
                            ? 'unlimited mileage'
                            : `${quote.mileage_km_per_day} km/day`}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {quote.requires_staff_quote && (
                        <Alert className="mb-4">
                            <AlertTriangle />
                            <AlertDescription>
                                Delivery to this zone is quoted by staff and is
                                not in the total.
                            </AlertDescription>
                        </Alert>
                    )}
                    <Table>
                        <TableBody>
                            {quote.lines.map((line) => (
                                <TableRow key={`${line.kind}-${line.code}`}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {line.label}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {line.unit &&
                                                `${line.quantity} × ${line.unit.display}`}
                                            {line.note && ` · ${line.note}`}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {line.counted ? (
                                            amount(line.amount)
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {amount(line.amount)}{' '}
                                                <Badge variant="outline">
                                                    not in total
                                                </Badge>
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <Summary label="Total" money={quote.total} />
                            <Summary
                                label={`Reservation (${percentFromBp(Number(quote.settings.reservation_percent_bp))})`}
                                money={quote.reservation}
                            />
                            <Summary
                                label="Balance at handover"
                                money={quote.balance}
                            />
                            <Summary
                                label={
                                    quote.deposit_band
                                        ? `Security deposit (${percentFromBp(quote.deposit_band.percent_bp)} of value, age ${quote.driver_age})`
                                        : 'Security deposit'
                                }
                                money={quote.deposit}
                                note={
                                    quote.deposit_hidden
                                        ? 'Needs permission to view car values'
                                        : quote.driver_age === null
                                          ? 'Enter a date of birth'
                                          : undefined
                                }
                            />
                        </TableFooter>
                    </Table>
                </CardContent>
            </Card>

            {currencies.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>In other currencies</CardTitle>
                        <CardDescription>
                            KES ÷ (market rate − margin), as renters see it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Currency</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Reservation
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Balance
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Deposit
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {currencies.map(([code, amounts]) => (
                                    <TableRow key={code}>
                                        <TableCell>
                                            <span className="font-mono">
                                                {code}
                                            </span>{' '}
                                            <span className="text-xs text-muted-foreground">
                                                at{' '}
                                                {Number(
                                                    quote.fx.rates[code]
                                                        ?.effective,
                                                )}
                                                {quote.fx.rates[code]?.stale &&
                                                    ' (stale)'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.total)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.reservation)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.balance)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.deposit)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            <p className="text-xs break-all text-muted-foreground">
                Settings hash {quote.settings_hash}
            </p>
        </>
    );
}

function Summary({
    label,
    money,
    note,
}: {
    label: string;
    money: Money | null;
    note?: string;
}) {
    return (
        <TableRow>
            <TableCell>
                {label}
                {note && (
                    <span className="block text-xs font-normal text-muted-foreground">
                        {note}
                    </span>
                )}
            </TableCell>
            <TableCell className="text-right tabular-nums">
                {amount(money)}
            </TableCell>
        </TableRow>
    );
}

QuoteSandbox.layout = {
    breadcrumbs: [{ title: 'Quote sandbox', href: QuoteSandboxController() }],
};
