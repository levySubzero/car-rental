<?php

namespace App\Http\Resources\Admin;

use App\Models\GatewayTransaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An online receipt for staff to resolve (M11B.9). The payer's phone is shown so staff can
 * refund to it.
 *
 * @mixin GatewayTransaction
 */
class GatewayReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $billRef = $this->payload['bill_ref'] ?? null;

        return [
            'id' => $this->id,
            'gateway' => $this->gateway->value,
            'gateway_label' => $this->gateway->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'open' => $this->status->awaitsStaff(),
            'receipt' => $this->receipt ?? $this->external_ref,
            'amount' => new Money($this->amount_minor),
            'payer_name' => $this->payer_name,
            'payer_phone' => $this->payer_phone,
            'account' => is_string($billRef) ? $billRef : null,
            'reason' => $this->failure_reason,
            'booking' => $this->booking === null ? null : [
                'reference' => $this->booking->reference,
                'status_label' => $this->booking->status->label(),
            ],
            'resolved_by' => $this->resolver?->name,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_note' => $this->resolution_note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
