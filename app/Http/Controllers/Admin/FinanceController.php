<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\FinanceRequest;
use App\Services\Finance\FinanceService;
use App\Services\Finance\LedgerEntry;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finance → Revenue (M11C): what was collected and refunded in a range of Nairobi days, by
 * day, channel and car, and the ledger behind it. Read-only; the export is its own permission.
 */
class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(FinanceRequest $request): Response
    {
        $period = $request->period();
        $report = $this->finance->report($period);

        return Inertia::render('admin/finance/index', [
            'filters' => ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()],
            'summary' => $report['summary'],
            'daily' => $report['daily'],
            'channels' => $report['channels'],
            'cars' => $report['cars'],
            // Rows are plain arrays; the resource collection gives the usual data, links and meta.
            'ledger' => JsonResource::collection($this->finance->paginateLedger($period, entries: $report['entries'])),
            'canExport' => $request->user()->can(Permission::FinanceExport),
        ]);
    }

    /**
     * The period's ledger oldest first, amounts in shillings. A byte-order mark lets
     * Excel read it as UTF-8.
     */
    public function export(FinanceRequest $request): StreamedResponse
    {
        $period = $request->period();
        $name = sprintf('baines-revenue-%s-%s.csv', $period->from->toDateString(), $period->to->toDateString());

        return response()->streamDownload(function () use ($period) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['Date', 'Time', 'Type', 'Booking', 'Car', 'Channel', 'Reference', 'Amount (KES)']);

            foreach ($this->finance->entries($period) as $entry) {
                /** @var LedgerEntry $entry */
                fputcsv($out, [
                    $entry->date(),
                    $entry->at->setTimezone((string) config('baines.timezone'))->format('H:i'),
                    $entry->kindLabel(),
                    $entry->bookingReference,
                    $entry->carName,
                    $entry->channel?->label(),
                    $entry->reference,
                    number_format($entry->amountMinor / 100, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
