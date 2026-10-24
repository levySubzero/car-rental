<?php

namespace App\Http\Requests\Admin\Booking;

use App\Models\DeliveryZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Set the delivery fee, or move the booking to another zone (M11.13). A zone with its own
 * fee needs none from staff.
 */
class UpdateDeliveryRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'updateDelivery';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_zone_id' => ['nullable', 'integer', Rule::exists(DeliveryZone::class, 'id')->where('is_active', true)],
            'fee' => [...$this->amountRules(false), 'required_without:delivery_zone_id'],
        ];
    }

    public function zone(): ?DeliveryZone
    {
        $id = $this->validated('delivery_zone_id');

        return $id === null ? null : DeliveryZone::query()->findOrFail((int) $id);
    }
}
