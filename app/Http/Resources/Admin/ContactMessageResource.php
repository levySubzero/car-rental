<?php

namespace App\Http\Resources\Admin;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactMessage
 */
class ContactMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'preferred_dates' => $this->preferred_dates,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'car' => $this->whenLoaded('car', fn () => $this->car ? ['name' => $this->car->name, 'slug' => $this->car->slug] : null),
            'handled_by' => $this->whenLoaded('handler', fn () => $this->handler?->name),
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
