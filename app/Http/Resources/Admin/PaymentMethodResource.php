<?php

namespace App\Http\Resources\Admin;

use App\Models\PaymentMethod;
use App\Services\Payments\OnlinePaymentService;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = $this->metadata?->getArrayCopy() ?? [];

        return [
            'id' => $this->id,
            'code' => $this->code->value,
            'name' => $this->name,
            'instructions' => $this->instructions,
            'requires_transaction_message' => $this->requires_transaction_message,
            'requires_proof' => $this->requires_proof,
            'is_active' => $this->is_active,
            // Online payments (M11B.8): staff's switch, and whether the gateway's keys are set.
            'supports_online' => $this->code->supportsOnline(),
            'online_enabled' => $this->online_enabled,
            'gateway_configured' => app(OnlinePaymentService::class)->gatewayConfigured($this->code),
            'sort_order' => $this->sort_order,
            'metadata' => (object) $metadata,
            // Still holds a TODO from the seed pack (D26).
            'has_todo' => Markdown::hasTodo($this->instructions) || Markdown::hasTodo(implode("\n", $metadata)),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
