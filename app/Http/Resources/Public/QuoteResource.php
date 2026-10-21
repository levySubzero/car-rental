<?php

namespace App\Http\Resources\Public;

use App\Services\Pricing\Quote\Quote;
use App\Services\Pricing\Quote\QuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A price preview for renters. It leaves out the deposit band's percentage, which with the
 * deposit would give away the car's value, and the internal settings and FX snapshot.
 *
 * @property Quote $resource
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            'days' => $quote->days,
            'lines' => array_map(fn (QuoteLine $line) => [
                'code' => $line->code,
                'label' => $line->label,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'amount' => $line->amount,
                'counted' => $line->counted,
                'note' => $line->note,
            ], $quote->lines),
            'total' => $quote->total,
            'reservation' => $quote->reservation,
            'balance' => $quote->balance,
            'deposit' => $quote->deposit,
            'mileage_km_per_day' => $quote->mileageKmPerDay,
            'requires_staff_quote' => $quote->requiresStaffQuote,
            'display' => $quote->display,
        ];
    }
}
