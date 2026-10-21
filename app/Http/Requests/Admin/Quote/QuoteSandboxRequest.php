<?php

namespace App\Http\Requests\Admin\Quote;

use App\Enums\Permission;
use App\Models\Car;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The quote sandbox's query string (M6.15). Every field is optional so the empty form loads;
 * a quote is made once a car and both times are given. Times are local business time
 * (datetime-local inputs), so a scenario reads the same for everyone who opens the link.
 * Past dates are allowed: Gate G3 replays the client's spreadsheet cases.
 */
class QuoteSandboxRequest extends FormRequest
{
    private const LOCAL = 'Y-m-d\TH:i';

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::QuotesSandbox) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'car' => ['nullable', 'string', Rule::exists('cars', 'slug')->whereNull('deleted_at')],
            'pickup_at' => ['nullable', 'date_format:'.self::LOCAL],
            'return_at' => ['nullable', 'date_format:'.self::LOCAL, 'after:pickup_at'],
            'extras' => ['array'],
            // Codes of active charges offered as extras (D32).
            'extras.*' => ['string', 'distinct', Rule::exists('charges', 'code')->where('is_active', true)->where('applies', 'extra')],
            'delivery_zone_id' => ['nullable', 'integer'],
            'driver_dob' => ['nullable', 'date_format:Y-m-d', 'before:today'],
        ];
    }

    public function isComplete(): bool
    {
        return filled($this->validated('car')) && filled($this->validated('pickup_at')) && filled($this->validated('return_at'));
    }

    public function car(): Car
    {
        return Car::query()->where('slug', $this->validated('car'))->sole();
    }

    public function pickup(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(self::LOCAL, (string) $this->validated('pickup_at'), (string) config('baines.timezone'))->startOfMinute();
    }

    public function returnAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(self::LOCAL, (string) $this->validated('return_at'), (string) config('baines.timezone'))->startOfMinute();
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

        return blank($zone) ? null : (int) $zone;
    }

    public function driverDob(): ?CarbonImmutable
    {
        $dob = $this->validated('driver_dob');

        return blank($dob) ? null : CarbonImmutable::createFromFormat('Y-m-d', (string) $dob)->startOfDay();
    }

    /**
     * The form's current values, echoed back to the page.
     *
     * @return array{car: string, pickup_at: string, return_at: string, extras: list<string>, delivery_zone_id: string, driver_dob: string}
     */
    public function formValues(): array
    {
        return [
            'car' => (string) $this->validated('car', ''),
            'pickup_at' => (string) $this->validated('pickup_at', ''),
            'return_at' => (string) $this->validated('return_at', ''),
            'extras' => array_values(array_map('strval', (array) $this->validated('extras', []))),
            'delivery_zone_id' => (string) $this->validated('delivery_zone_id', ''),
            'driver_dob' => (string) $this->validated('driver_dob', ''),
        ];
    }
}
