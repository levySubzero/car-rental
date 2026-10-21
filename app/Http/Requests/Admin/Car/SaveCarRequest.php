<?php

namespace App\Http\Requests\Admin\Car;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Car;
use App\Support\Money;
use App\Support\Rules\MaxMediaForCollection;
use App\Support\Rules\MetadataKeys;
use App\Support\Rules\PendingOrOwnedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Creates a car, or updates the car bound to the route.
 *
 * Amounts are typed in major units ("15000" or "15000.50") and reach the service in minor
 * units through carData(). `car_value` is only read from staff with cars.view-value; for
 * everyone else it is ignored and the stored value is kept.
 */
class SaveCarRequest extends FormRequest
{
    private const AMOUNT = 'regex:/^\d{1,12}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $car = $this->car();

        return $car === null
            ? ($this->user()?->can('create', Car::class) ?? false)
            : ($this->user()?->can('update', $car) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'car_type_id' => ['required', 'integer', 'exists:car_types,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('cars', 'slug')->ignore($this->car())],
            'model_year' => ['nullable', 'integer', 'between:1950,'.(now()->year + 1)],
            'engine_type' => ['nullable', 'string', 'max:80'],
            'engine_capacity_litres' => ['nullable', 'numeric', 'decimal:0,1', 'between:0.6,10'],
            'fuel_type' => ['required', Rule::enum(FuelType::class)],
            'transmission' => ['required', Rule::enum(Transmission::class)],
            'colour' => ['nullable', 'string', 'max:40'],
            'doors' => ['required', 'integer', 'between:1,8'],
            'seats' => ['required', 'integer', 'between:1,60'],
            'daily_rate' => ['required', 'string', self::AMOUNT],
            'car_value' => ['nullable', 'string', self::AMOUNT],
            'mileage_limit_km' => ['nullable', 'integer', 'between:1,10000'],
            'damage_excess' => ['nullable', 'string', self::AMOUNT],
            // Any known currency, active or not: a car saved with one must stay saveable.
            'damage_excess_currency' => ['nullable', 'required_with:damage_excess', 'string', 'exists:currencies,code'],
            'description' => ['nullable', 'string', 'max:20000'],
            'metadata' => ['nullable', 'array', 'max:30', new MetadataKeys],
            'metadata.*' => ['nullable', 'string', 'max:500'],
            'gallery' => ['present', 'array', new MaxMediaForCollection('car.gallery')],
            'gallery.*' => ['uuid', 'distinct', new PendingOrOwnedMedia('car.gallery', $this->car())],
            'cover' => ['nullable', 'uuid', 'in_array:gallery.*'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'brand_id' => 'brand',
            'car_type_id' => 'car type',
            'metadata.*' => 'extra info value',
            'gallery.*' => 'photo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'daily_rate.regex' => 'Enter an amount such as 15000 or 15000.50.',
            'car_value.regex' => 'Enter an amount such as 4500000.',
            'damage_excess.regex' => 'Enter an amount such as 100000.',
        ];
    }

    /**
     * The validated input in the shape CarService expects: amounts in minor units, empty
     * extra info dropped, and the car value only for staff allowed to see it.
     *
     * @return array<string, mixed>
     */
    public function carData(): array
    {
        $data = $this->validated();
        $currency = $data['damage_excess_currency'] ?? null;

        $carData = [
            ...Arr::except($data, ['daily_rate', 'car_value', 'damage_excess']),
            'daily_rate_minor' => Money::fromMajor($data['daily_rate'])->minor,
            'damage_excess_minor' => $this->minor($data['damage_excess'] ?? null),
            'damage_excess_currency' => filled($data['damage_excess'] ?? null) ? $currency : null,
            'metadata' => array_filter(
                array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data['metadata'] ?? []),
                fn ($value) => filled($value),
            ),
        ];

        if ($this->user()?->can('viewValue', Car::class)) {
            $carData['car_value_minor'] = $this->minor($data['car_value'] ?? null);
        }

        return $carData;
    }

    private function minor(?string $amount): ?int
    {
        return filled($amount) ? Money::fromMajor((string) $amount)->minor : null;
    }

    private function car(): ?Car
    {
        $car = $this->route('car');

        return $car instanceof Car ? $car : null;
    }
}
