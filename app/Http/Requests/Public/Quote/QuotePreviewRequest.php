<?php

namespace App\Http\Requests\Public\Quote;

use App\Models\Car;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A renter's price preview (M6.17): any visitor, rate limited. Times are ISO instants.
 */
class QuotePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'car' => ['required', 'string', Rule::exists('cars', 'slug')->where('is_published', true)->whereNull('deleted_at')],
            'pickup_at' => ['required', 'date', 'after:now'],
            'return_at' => ['required', 'date', 'after:pickup_at', 'before:+1 year'],
            'extras' => ['array', 'max:10'],
            // Codes of active charges offered as extras (D32).
            'extras.*' => ['string', 'distinct', Rule::exists('charges', 'code')->where('is_active', true)->where('applies', 'extra')],
            'delivery_zone_id' => ['nullable', 'integer', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'driver_dob' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
        ];
    }

    public function car(): Car
    {
        return Car::query()->published()->where('slug', $this->validated('car'))->sole();
    }

    public function pickup(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('pickup_at'));
    }

    public function returnAt(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('return_at'));
    }

    /**
     * Codes of the extras the renter picked.
     *
     * @return list<string>
     */
    public function extras(): array
    {
        return array_values(array_map('strval', (array) $this->validated('extras', [])));
    }

    public function zoneId(): ?int
    {
        $zone = $this->validated('delivery_zone_id');

        return $zone === null ? null : (int) $zone;
    }

    public function driverDob(): ?CarbonImmutable
    {
        $dob = $this->validated('driver_dob');

        return $dob === null ? null : CarbonImmutable::parse((string) $dob);
    }
}
