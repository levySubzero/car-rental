<?php

namespace App\Http\Resources\Public;

use App\Models\PaymentMethod;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method for the booking flow (M9). Instructions are shown only after the renter
 * picks the method (PRD P-1); `metadata` stays internal (D20).
 *
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code->value,
            'name' => $this->name,
            'requires_transaction_message' => $this->requires_transaction_message,
            'requires_proof' => $this->requires_proof,
            'instructions_html' => Markdown::toHtml($this->instructions),
        ];
    }
}
